<?php

namespace App\Livewire;

use App\Models\Quote;
use App\Models\QuoteDocument;
use App\Support\PdfDocuments;
use App\Support\QuotePipeline;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The client's second signature: they review the formal proposal behind a private link and sign
 * it. The carrier's name is never loaded into this page.
 */
#[Layout('layouts.public')]
class ProposalAccept extends Component
{
    public Quote $quote;

    public string $locale = 'en';

    /** '' while working, 'accepted' right after signing. */
    public string $done = '';

    public bool $disclosureAccepted = false;

    public string $signerName = '';

    public ?string $signatureData = null;

    public function mount(string $token): void
    {
        $this->quote = Quote::with(['coverages.type', 'application'])
            ->where('acceptance_token', $token)
            ->firstOrFail();

        $this->locale = $this->quote->application->locale ?? 'en';
        App::setLocale($this->locale);
    }

    /** Livewire skips mount() on later requests: keep the chosen language. */
    public function hydrate(): void
    {
        App::setLocale($this->locale);
    }

    public function switchLocale(string $locale): void
    {
        if (in_array($locale, ['en', 'es'], true)) {
            $this->locale = $locale;
            App::setLocale($locale);
        }
    }

    public function state(): string
    {
        return $this->quote->clientState();
    }

    public function sign(): void
    {
        abort_unless($this->state() === 'open', 410);

        $this->validate([
            'signerName' => 'required|string|max:255',
            'disclosureAccepted' => 'accepted',
            'signatureData' => 'required|string',
        ]);

        DB::transaction(function (): void {
            // Re-read under lock: a second tab or a revoked link must not slip through.
            $this->quote = Quote::with(['coverages.type', 'application'])->lockForUpdate()->findOrFail($this->quote->id);
            abort_unless($this->state() === 'open', 410);

            $png = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $this->signatureData));
            $path = "quote-signatures/{$this->quote->id}.png";
            Storage::disk(QuoteDocument::DISK)->put($path, $png);

            QuotePipeline::accept($this->quote, $this->signerName, $path, request()->ip());
        });

        $this->quote->refresh();

        // The PDF exactly as signed. A rendering problem must not lose the acceptance.
        try {
            PdfDocuments::storeAcceptedProposal($this->quote);
        } catch (\Throwable $e) {
            report($e);
        }

        $this->done = 'accepted';
    }

    public function render()
    {
        return view('livewire.proposal-accept', ['state' => $this->state()]);
    }
}

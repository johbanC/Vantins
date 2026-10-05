<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Support\PdfDocuments;

/**
 * The client's own downloads, behind the proposal's private link: the proposal while it is open,
 * their signed copy afterwards, and the binder once it exists. Never anything with the carrier.
 */
class ProposalPdfController extends Controller
{
    public function proposal(string $token, ?string $locale = null)
    {
        $quote = $this->quote($token);
        abort_unless(in_array($quote->clientState(), ['open', 'accepted'], true), 404);

        PdfDocuments::logDownload($quote, 'proposal');

        return QuotePdfController::deliver($quote, 'proposal', $this->locale($quote, $locale));
    }

    public function binder(string $token, ?string $locale = null)
    {
        $quote = $this->quote($token);
        abort_unless($quote->clientState() === 'accepted' && $quote->hasBinder(), 404);

        PdfDocuments::logDownload($quote, 'binder');

        return QuotePdfController::deliver($quote, 'binder', $this->locale($quote, $locale));
    }

    public function signed(string $token)
    {
        $quote = $this->quote($token);
        abort_unless($quote->clientState() === 'accepted', 404);

        PdfDocuments::logDownload($quote, 'proposal_signed');

        return QuotePdfController::deliver($quote, 'signed', $this->locale($quote, null));
    }

    protected function quote(string $token): Quote
    {
        return Quote::with(['application', 'coverages.type'])->where('acceptance_token', $token)->firstOrFail();
    }

    protected function locale(Quote $quote, ?string $locale): string
    {
        return in_array($locale, ['en', 'es'], true) ? $locale : ($quote->application->locale ?? 'en');
    }
}

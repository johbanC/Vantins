<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Support\PdfDocuments;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** The quote's documents for staff: proposal, binder and the copy the client signed. */
class QuotePdfController extends Controller
{
    public function staff(Quote $quote, string $kind, ?string $locale = null)
    {
        Gate::authorize('view', $quote);

        $locale = in_array($locale, ['en', 'es'], true) ? $locale : ($quote->application->locale ?? 'en');

        PdfDocuments::logDownload($quote, 'quote_'.$kind);

        return static::deliver($quote, $kind, $locale);
    }

    /** Shared with the client's token routes. */
    public static function deliver(Quote $quote, string $kind, string $locale)
    {
        $name = 'Vantins-'.str($quote->application->company_name ?: 'quote')->slug().'-'.$kind;

        return match ($kind) {
            'proposal' => PdfDocuments::proposal($quote, $locale)->stream($name.'-'.$locale.'.pdf'),
            'binder' => PdfDocuments::binder($quote, $locale)->stream($name.'-'.$locale.'.pdf'),
            'signed' => static::signed($quote, $name),
            default => abort(404),
        };
    }

    protected static function signed(Quote $quote, string $name)
    {
        abort_unless($quote->accepted_at, 404);

        $path = $quote->accepted_pdf_path && Storage::disk(PdfDocuments::DISK)->exists($quote->accepted_pdf_path)
            ? $quote->accepted_pdf_path
            : PdfDocuments::storeAcceptedProposal($quote);

        return Storage::disk(PdfDocuments::DISK)->response($path, $name.'-signed.pdf', ['Content-Type' => 'application/pdf']);
    }
}

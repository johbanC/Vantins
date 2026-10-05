<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Quote;
use App\Models\QuoteDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the three documents (application summary, formal proposal, binder) and keeps the copy
 * the client signed. Each is rendered in a chosen language without disturbing the request's own.
 */
class PdfDocuments
{
    public const DISK = QuoteDocument::DISK;

    /** Application summary, with or without the client's signature. */
    public static function application(Application $application, string $locale): DomPdf
    {
        $application->loadMissing(['drivers', 'vehicles', 'trailers', 'coverages.type']);

        return static::inLocale($locale, fn () => Pdf::loadView('pdf.application', [
            'application' => $application,
            'qr' => static::qr(route('verify', $application->verification_code)),
            'signature' => $application->signature_path && Storage::disk('public')->exists($application->signature_path)
                ? 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($application->signature_path))
                : null,
            'representativeSignature' => static::representativeSignature(),
            'representativeName' => config('vantins.representative_name'),
            'representativeTitle' => config('vantins.representative_title'),
        ])->setPaper('letter'));
    }

    /** The formal proposal: from the signed snapshot when accepted, from the live quote before that. */
    public static function proposal(Quote $quote, string $locale): DomPdf
    {
        $quote->loadMissing('application');

        return static::inLocale($locale, fn () => Pdf::loadView('pdf.proposal', static::quoteData($quote, $locale) + [
            'accepted' => $quote->accepted_at !== null,
            'signerName' => $quote->accepted_signer_name,
            'signedAt' => $quote->accepted_at ? Format::date($quote->accepted_at) : null,
            'signature' => static::signatureUri($quote->accepted_signature_path),
        ])->setPaper('letter'));
    }

    /** Binder / issuance document. The carrier appears only when an admin authorised it. */
    public static function binder(Quote $quote, string $locale): DomPdf
    {
        abort_unless($quote->hasBinder(), 404);
        $quote->loadMissing('application', 'carrier');

        $status = match (true) {
            $quote->stage === 'sold' => 'binder_status_sold',
            $quote->stage === 'paid' => 'binder_status_paid',
            default => 'binder_status_issued',
        };

        return static::inLocale($locale, fn () => Pdf::loadView('pdf.binder', static::quoteData($quote, $locale) + [
            'quote' => $quote,
            'carrierName' => $quote->carrierNameForBinder(),
            'statusLabel' => __('app.'.$status),
        ])->setPaper('letter'));
    }

    /**
     * What both quote documents print: the terms as signed (or as quoted, before signing), the agency
     * block and a QR pointing at the verification page. Never the carrier.
     *
     * @return array<string, mixed>
     */
    protected static function quoteData(Quote $quote, string $locale): array
    {
        $application = $quote->application;

        return [
            'd' => $quote->accepted_snapshot ?? QuotePipeline::snapshot($quote),
            'locale' => $locale,
            'reference' => $quote->verification_code,
            'issued' => Format::date($quote->accepted_at ?? $quote->sent_at ?? $quote->created_at),
            'isDemo' => $quote->is_demo,
            'agencyName' => $application->agency_name,
            'agencyPhone' => $application->agency_phone,
            'contactAgent' => $application->contact_agent_name,
            'qr' => static::qr(route('verify', $quote->verification_code)),
        ];
    }

    // ------------------------------------------------------------------ signed copies ----

    /**
     * Freeze the application exactly as the client signed it: a PDF in the language of the
     * signature, stored privately and never rewritten.
     */
    public static function storeSignedApplication(Application $application): string
    {
        $path = 'signed-documents/applications/'.$application->id.'-'.now()->format('Ymd-His').'.pdf';

        Storage::disk(self::DISK)->put($path, static::application($application, $application->locale)->output());
        $application->forceFill(['pdf_path' => $path])->saveQuietly();

        return $path;
    }

    /** The stored signed copy; records signed before this existed get theirs the first time it is asked for. */
    public static function signedApplicationPath(Application $application): ?string
    {
        if (! $application->isLocked()) {
            return null;
        }

        if (! $application->pdf_path || ! Storage::disk(self::DISK)->exists($application->pdf_path)) {
            return static::storeSignedApplication($application);
        }

        return $application->pdf_path;
    }

    public static function storeAcceptedProposal(Quote $quote): string
    {
        $locale = $quote->application->locale ?? 'en';
        $path = 'signed-documents/proposals/'.$quote->id.'-'.now()->format('Ymd-His').'.pdf';

        Storage::disk(self::DISK)->put($path, static::proposal($quote->refresh(), $locale)->output());
        $quote->forceFill(['accepted_pdf_path' => $path])->saveQuietly();

        return $path;
    }

    /** Every download of a document is part of the audit trail (the client's too: no user, with the IP). */
    public static function logDownload(Model $subject, string $document): void
    {
        ActivityLog::record($subject, 'downloaded', ['document' => [null, $document]]);
    }

    // ------------------------------------------------------------------------ helpers ----

    public static function qr(string $url): string
    {
        return (new PngWriter)->write(new QrCode(data: $url, size: 220, margin: 4))->getDataUri();
    }

    /** The legal representative's countersignature (a static image shipped with the app), or null when absent. */
    protected static function representativeSignature(): ?string
    {
        $relative = config('vantins.representative_signature');
        $path = $relative ? public_path($relative) : null;

        if (! $path || ! is_file($path)) {
            return null;
        }

        return 'data:image/'.pathinfo($path, PATHINFO_EXTENSION).';base64,'.base64_encode(file_get_contents($path));
    }

    protected static function signatureUri(?string $path): ?string
    {
        return $path && Storage::disk(self::DISK)->exists($path)
            ? 'data:image/png;base64,'.base64_encode(Storage::disk(self::DISK)->get($path))
            : null;
    }

    /** Render while the app speaks the document's language, then give the request its own back. */
    protected static function inLocale(string $locale, \Closure $render): DomPdf
    {
        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            return $render();
        } finally {
            App::setLocale($previous);
        }
    }
}

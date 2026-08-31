<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

class ApplicationPdfController extends Controller
{
    public function show(string $token, ?string $locale = null)
    {
        $application = Application::with(['drivers', 'vehicles', 'trailers', 'coverages'])
            ->where('token', $token)
            ->firstOrFail();

        // Both language versions are always available; default to how it was filled.
        $locale = in_array($locale, ['en', 'es'], true) ? $locale : $application->locale;
        App::setLocale($locale);

        $qr = (new PngWriter)->write(
            new QrCode(
                data: route('verify', $application->verification_code),
                size: 220,
                margin: 4,
            )
        );

        $signatureDataUri = null;
        if ($application->signature_path && Storage::disk('public')->exists($application->signature_path)) {
            $signatureDataUri = 'data:image/png;base64,'
                .base64_encode(Storage::disk('public')->get($application->signature_path));
        }

        $pdf = Pdf::loadView('pdf.application', [
            'application' => $application,
            'qr' => $qr->getDataUri(),
            'signature' => $signatureDataUri,
            'representativeSignature' => $this->representativeSignature(),
            'representativeName' => config('vantins.representative_name'),
            'representativeTitle' => config('vantins.representative_title'),
        ])->setPaper('letter');

        $name = 'Vantins-'.str($application->company_name ?: 'application')->slug().'-'.$locale.'.pdf';

        return $pdf->stream($name);
    }

    /** Static legal-representative signature image as a data URI, or null when not configured. */
    private function representativeSignature(): ?string
    {
        $relative = config('vantins.representative_signature');

        if (! $relative) {
            return null;
        }

        $path = public_path($relative);

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/'.pathinfo($path, PATHINFO_EXTENSION).';base64,'.base64_encode(file_get_contents($path));
    }
}

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
    public function show(string $token)
    {
        $application = Application::with(['drivers', 'vehicles', 'trailers', 'coverages'])
            ->where('token', $token)
            ->firstOrFail();

        App::setLocale($application->locale);

        $qr = (new PngWriter())->write(
            new QrCode(
                data: route('verify', $application->verification_code),
                size: 220,
                margin: 4,
            )
        );

        $signatureDataUri = null;
        if ($application->signature_path && Storage::disk('public')->exists($application->signature_path)) {
            $signatureDataUri = 'data:image/png;base64,'
                . base64_encode(Storage::disk('public')->get($application->signature_path));
        }

        $pdf = Pdf::loadView('pdf.application', [
            'application' => $application,
            'qr' => $qr->getDataUri(),
            'signature' => $signatureDataUri,
        ])->setPaper('letter');

        $name = 'Vantins-'.str($application->company_name ?: 'application')->slug().'.pdf';

        return $pdf->stream($name);
    }
}

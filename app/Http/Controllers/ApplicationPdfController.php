<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Support\PdfDocuments;
use Illuminate\Support\Facades\Storage;

class ApplicationPdfController extends Controller
{
    /** The summary as it is now, in either language (staff share this link). */
    public function show(string $token, ?string $locale = null)
    {
        $application = Application::where('token', $token)->firstOrFail();

        // Both language versions are always available; default to how it was filled.
        $locale = in_array($locale, ['en', 'es'], true) ? $locale : $application->locale;

        PdfDocuments::logDownload($application, 'application_'.$locale);

        $name = 'Vantins-'.str($application->company_name ?: 'application')->slug().'-'.$locale.'.pdf';

        return PdfDocuments::application($application, $locale)->stream($name);
    }

    /** The copy the client signed, exactly as it was when they signed it. */
    public function signed(string $token)
    {
        $application = Application::where('token', $token)->firstOrFail();
        $path = PdfDocuments::signedApplicationPath($application);

        abort_unless($path, 404);

        PdfDocuments::logDownload($application, 'application_signed');

        return Storage::disk(PdfDocuments::DISK)->response(
            $path,
            'Vantins-'.str($application->company_name ?: 'application')->slug().'-signed.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}

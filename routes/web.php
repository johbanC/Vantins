<?php

use App\Http\Controllers\ApplicationPdfController;
use App\Http\Controllers\QuoteDocumentController;
use App\Livewire\ApplyForm;
use App\Livewire\ProposalAccept;
use App\Models\Application;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

// Staff panel UI language preference.
Route::get('/panel/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['en', 'es'], true), 404);
    if ($user = auth()->user()) {
        $user->forceFill(['locale' => $locale])->save();
    }

    return back();
})->middleware('auth')->name('panel.locale');

// Client-facing application form (tokenised link, no auth).
Route::get('/apply/{token}', ApplyForm::class)->name('apply.show');

// The client's second signature: the formal proposal of one quote (tokenised link, no auth).
Route::get('/proposal/{token}', ProposalAccept::class)->name('proposal.show');

// Quote files (staff only, authorised per quote, downloads are audited).
Route::get('/quote-documents/{document}', [QuoteDocumentController::class, 'download'])
    ->middleware('auth')
    ->name('quote-documents.download');

// Branded PDF of an application, in either language (staff share this).
Route::get('/applications/{token}/pdf/{locale?}', [ApplicationPdfController::class, 'show'])
    ->whereIn('locale', ['en', 'es'])
    ->name('applications.pdf');

// Public document verification (QR target).
Route::get('/verify/{code}', function (string $code) {
    $application = Application::where('verification_code', $code)->first();

    return response()->view('verify', [
        'application' => $application,
        'code' => $code,
    ]);
})->name('verify');

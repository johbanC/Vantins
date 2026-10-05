<?php

use App\Http\Controllers\ApplicationPdfController;
use App\Http\Controllers\ProposalPdfController;
use App\Http\Controllers\QuoteDocumentController;
use App\Http\Controllers\QuotePdfController;
use App\Livewire\ApplyForm;
use App\Livewire\ProposalAccept;
use App\Models\Application;
use App\Models\Quote;
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
Route::get('/proposal/{token}/pdf/{locale?}', [ProposalPdfController::class, 'proposal'])->whereIn('locale', ['en', 'es'])->name('proposal.pdf');
Route::get('/proposal/{token}/binder/{locale?}', [ProposalPdfController::class, 'binder'])->whereIn('locale', ['en', 'es'])->name('proposal.binder');
Route::get('/proposal/{token}/signed', [ProposalPdfController::class, 'signed'])->name('proposal.signed');

// Quote documents for staff: proposal, binder, signed copy.
Route::get('/quotes/{quote}/pdf/{kind}/{locale?}', [QuotePdfController::class, 'staff'])
    ->whereIn('kind', ['proposal', 'binder', 'signed'])
    ->whereIn('locale', ['en', 'es'])
    ->middleware('auth')
    ->name('quotes.pdf');

// Quote files (staff only, authorised per quote, downloads are audited).
Route::get('/quote-documents/{document}', [QuoteDocumentController::class, 'download'])
    ->middleware('auth')
    ->name('quote-documents.download');

// Branded PDF of an application, in either language (staff share this).
Route::get('/applications/{token}/pdf/{locale?}', [ApplicationPdfController::class, 'show'])
    ->whereIn('locale', ['en', 'es'])
    ->name('applications.pdf');

// The copy the client signed, exactly as it was (tokenised, like the application link).
Route::get('/applications/{token}/signed', [ApplicationPdfController::class, 'signed'])->name('applications.signed');

// Public document verification (QR target).
Route::get('/verify/{code}', function (string $code) {
    $application = Application::where('verification_code', $code)->first();
    $quote = $application ? null : Quote::with('application')->where('verification_code', $code)->first();

    return response()->view('verify', [
        'application' => $application,
        'quote' => $quote,
        'code' => $code,
    ]);
})->name('verify');

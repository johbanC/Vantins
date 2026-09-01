<?php

use App\Http\Controllers\ApplicationPdfController;
use App\Livewire\ApplyForm;
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

// Branded PDF of an application, in either language (staff share this).
Route::get('/applications/{token}/pdf/{locale?}', [ApplicationPdfController::class, 'show'])
    ->whereIn('locale', ['en', 'es'])
    ->name('applications.pdf');

// Welcome letter, sent to the client once the document is signed.
Route::get('/applications/{token}/welcome-letter/{locale?}', [ApplicationPdfController::class, 'welcomeLetter'])
    ->whereIn('locale', ['en', 'es'])
    ->name('applications.welcome-letter');

// Public document verification (QR target).
Route::get('/verify/{code}', function (string $code) {
    $application = Application::where('verification_code', $code)->first();

    return response()->view('verify', [
        'application' => $application,
        'code' => $code,
    ]);
})->name('verify');

<?php

use App\Http\Controllers\ApplicationPdfController;
use App\Livewire\ApplyForm;
use App\Models\Application;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

// Client-facing application form (tokenised link, no auth).
Route::get('/apply/{token}', ApplyForm::class)->name('apply.show');

// Branded PDF of an application (staff share this / QR points at verify).
Route::get('/applications/{token}/pdf', [ApplicationPdfController::class, 'show'])
    ->name('applications.pdf');

// Public document verification (QR target).
Route::get('/verify/{code}', function (string $code) {
    $application = Application::where('verification_code', $code)->first();

    return response()->view('verify', [
        'application' => $application,
        'code' => $code,
    ]);
})->name('verify');

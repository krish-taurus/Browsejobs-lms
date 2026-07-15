<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\MagicLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Magic-link consume-and-redirect. `signed` validates the token has not been
// tampered with; the controller enforces single-use + expiry and logs the user in.
Route::get('/magic/{token}', [MagicLinkController::class, 'consume'])
    ->name('magic.consume')
    ->middleware('signed');

// Google sign-in (session-based, so these live on web routes). 404s until
// GOOGLE_CLIENT_ID is configured.
Route::middleware('tenant.domain')->group(function () {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

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

// Self-hosted class recordings, streamed with HTTP Range support (the PHP dev
// server can't range-serve static files, and browsers need ranges to play
// MP4s whose moov atom sits at the end — every Zoom recording). The URL is
// handed out only after MyRecordingController's membership + fee gate.
Route::get('/media/recordings/{tenant}/{file}', function (int $tenant, string $file) {
    $path = storage_path("app/public/recordings/{$tenant}/{$file}");

    abort_unless(is_file($path), 404);

    return response()->file($path, ['Content-Type' => 'video/mp4', 'Accept-Ranges' => 'bytes']);
})->where('file', '[A-Za-z0-9._-]+\.mp4')->name('media.recording');

// Google sign-in (session-based, so these live on web routes). 404s until
// GOOGLE_CLIENT_ID is configured.
Route::middleware('tenant.domain')->group(function () {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

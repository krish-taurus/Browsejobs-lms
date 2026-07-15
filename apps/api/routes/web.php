<?php

declare(strict_types=1);

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

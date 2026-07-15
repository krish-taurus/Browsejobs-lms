<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\Webhooks\ZoomWebhookController;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

/*
| API routes. The `api` group (throttle:api + Sanctum stateful) is applied
| automatically. Auth uses the Sanctum SPA cookie flow (ADR 0004).
*/

// Public + auth endpoints, tenant resolved by request host.
Route::prefix('v1')->middleware('tenant.domain')->group(function () {
    Route::get('branding', function () {
        $tenant = app(TenantContext::class)->get();

        return response()->json([
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'branding' => $tenant->branding,
        ]);
    });

    Route::prefix('auth')->group(function () {
        Route::post('otp/request', [StudentAuthController::class, 'requestOtp'])->middleware('throttle:6,1');
        Route::post('otp/verify', [StudentAuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
        Route::post('staff/login', [StaffAuthController::class, 'login'])->middleware('throttle:6,1');
        Route::post('staff/2fa', [StaffAuthController::class, 'verify'])->middleware('throttle:10,1');
    });
});

// Authenticated session endpoints (Sanctum session guard).
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('me', [SessionController::class, 'me']);
    Route::post('logout', [SessionController::class, 'destroy']);
});

// Signature-verified inbound webhooks (no user auth).
Route::post('webhooks/zoom', ZoomWebhookController::class)
    ->middleware('zoom.signed')
    ->withoutMiddleware('throttle:api')
    ->name('webhooks.zoom');

<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\RosterController;
use App\Http\Controllers\Admin\TopicController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\Leads\LeadController;
use App\Http\Controllers\Reviews\ReviewController;
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
            'google_auth' => (string) config('services.google.client_id') !== '',
        ]);
    });

    Route::post('leads', [LeadController::class, 'store'])
        ->middleware('throttle:10,1');

    Route::get('reviews', [ReviewController::class, 'index']);

    Route::prefix('auth')->group(function () {
        Route::post('otp/request', [StudentAuthController::class, 'requestOtp'])->middleware('throttle:6,1');
        Route::post('otp/verify', [StudentAuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
        Route::post('register/request', [RegisterController::class, 'request'])->middleware('throttle:6,1');
        Route::post('register/verify', [RegisterController::class, 'verify'])->middleware('throttle:10,1');
        Route::post('staff/login', [StaffAuthController::class, 'login'])->middleware('throttle:6,1');
        Route::post('staff/2fa', [StaffAuthController::class, 'verify'])->middleware('throttle:10,1');
    });
});

// Authenticated session endpoints (Sanctum session guard).
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('me', [SessionController::class, 'me']);
    Route::post('logout', [SessionController::class, 'destroy']);
});

// Admin panel API. Tenant resolves from the authenticated user; every route is
// additionally gated by the permission its role grants (Gate::before resolves
// slugs; super-admin bypasses).
Route::middleware(['auth:sanctum', 'tenant.user'])->prefix('v1/admin')->group(function () {
    Route::middleware('can:manage-curriculum')->group(function () {
        Route::get('courses', [CurriculumController::class, 'index']);
        Route::get('courses/{course}', [CurriculumController::class, 'show']);
        Route::post('modules', [ModuleController::class, 'store']);
        Route::patch('modules/{module}', [ModuleController::class, 'update']);
        Route::delete('modules/{module}', [ModuleController::class, 'destroy']);
        Route::post('topics', [TopicController::class, 'store']);
        Route::patch('topics/{topic}', [TopicController::class, 'update']);
        Route::delete('topics/{topic}', [TopicController::class, 'destroy']);
        Route::post('lessons', [LessonController::class, 'store']);
        Route::patch('lessons/{lesson}', [LessonController::class, 'update']);
        Route::delete('lessons/{lesson}', [LessonController::class, 'destroy']);
    });

    Route::middleware('can:manage-batches')->group(function () {
        Route::get('batches', [BatchController::class, 'index']);
        Route::post('batches', [BatchController::class, 'store']);
        Route::get('batches/{batch}', [BatchController::class, 'show']);
    });

    Route::middleware('can:manage-rosters')->group(function () {
        Route::post('batches/{batch}/members', [RosterController::class, 'store']);
        Route::post('batches/{batch}/import', [RosterController::class, 'import']);
        Route::post('members/{member}/transfer', [RosterController::class, 'transfer']);
        Route::post('members/{member}/remove', [RosterController::class, 'remove']);
    });
});

// Signature-verified inbound webhooks (no user auth).
Route::post('webhooks/zoom', ZoomWebhookController::class)
    ->middleware('zoom.signed')
    ->withoutMiddleware('throttle:api')
    ->name('webhooks.zoom');

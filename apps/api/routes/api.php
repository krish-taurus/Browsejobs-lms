<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\Admin\CrmAssignmentRuleController;
use App\Http\Controllers\Admin\CrmTaskController;
use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\DunningController;
use App\Http\Controllers\Admin\FeePlanController;
use App\Http\Controllers\Admin\LeadAdminController;
use App\Http\Controllers\Admin\LeadStageController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\MessageTemplateController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\PaymentLinkController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\RosterController;
use App\Http\Controllers\Admin\TopicController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\FeeStatusController;
use App\Http\Controllers\Leads\LeadController;
use App\Http\Controllers\MessagePreferenceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Reviews\ReviewController;
use App\Http\Controllers\Webhooks\MetaLeadController;
use App\Http\Controllers\Webhooks\RazorpayWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppController;
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
    Route::get('me/fee-status', [FeeStatusController::class, 'show']);
    Route::get('me/notifications', [NotificationController::class, 'index']);
    Route::post('me/notifications/read', [NotificationController::class, 'markRead']);
    Route::get('me/message-preferences', [MessagePreferenceController::class, 'show']);
    Route::put('me/message-preferences', [MessagePreferenceController::class, 'update']);
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

    // Built-in CRM (PRD §6.12).
    Route::middleware('can:manage-leads')->group(function () {
        Route::get('leads', [LeadAdminController::class, 'index']);
        Route::get('leads/board', [LeadAdminController::class, 'board']);
        Route::get('leads/counselors', [LeadAdminController::class, 'counselors']);
        Route::post('leads', [LeadAdminController::class, 'store']);
        Route::post('leads/import', [LeadAdminController::class, 'import']);
        Route::get('leads/{lead}', [LeadAdminController::class, 'show']);
        Route::post('leads/{lead}/assign', [LeadAdminController::class, 'assign']);
        Route::post('leads/{lead}/stage', [LeadAdminController::class, 'moveStage']);
        Route::post('leads/{lead}/merge', [LeadAdminController::class, 'merge']);
        Route::post('leads/{lead}/notes', [LeadAdminController::class, 'note']);

        Route::get('lead-stages', [LeadStageController::class, 'index']);

        Route::get('crm-tasks', [CrmTaskController::class, 'index']);
        Route::post('crm-tasks', [CrmTaskController::class, 'store']);
        Route::post('crm-tasks/{task}/complete', [CrmTaskController::class, 'complete']);

        Route::get('crm-assignment-rule', [CrmAssignmentRuleController::class, 'show']);
        Route::put('crm-assignment-rule', [CrmAssignmentRuleController::class, 'update']);
    });

    // Payments + EMI (PRD §6.8).
    Route::middleware('can:manage-fees')->group(function () {
        Route::get('fee-plans', [FeePlanController::class, 'index']);
        Route::get('fee-plans/batches', [FeePlanController::class, 'batches']);
        Route::get('fee-plans/candidates', [FeePlanController::class, 'candidates']);
        Route::post('fee-plans/preview', [FeePlanController::class, 'preview']);
        Route::post('fee-plans/bulk-links', [PaymentLinkController::class, 'bulk']);
        Route::post('fee-plans', [FeePlanController::class, 'store']);
        Route::get('fee-plans/{feePlan}', [FeePlanController::class, 'show']);

        Route::post('instalments/{instalment}/link', [PaymentLinkController::class, 'store']);
        Route::post('instalments/{instalment}/order', [PaymentLinkController::class, 'order']);

        Route::get('receipts/{receipt}/download', [ReceiptController::class, 'download']);

        Route::get('dunning', [DunningController::class, 'index']);
    });

    // Messaging hub (PRD §6.9).
    Route::middleware('can:manage-messaging')->group(function () {
        Route::get('messages', [MessageController::class, 'index']);
        Route::get('message-templates', [MessageTemplateController::class, 'index']);
        Route::post('message-templates/preview', [MessageTemplateController::class, 'preview']);
        Route::post('message-templates', [MessageTemplateController::class, 'store']);
        Route::put('message-templates/{template}', [MessageTemplateController::class, 'update']);
    });
});

// Signature-verified inbound webhooks (no user auth).
Route::post('webhooks/zoom', ZoomWebhookController::class)
    ->middleware('zoom.signed')
    ->withoutMiddleware('throttle:api')
    ->name('webhooks.zoom');

// Meta Lead Ads (PRD §6.12). GET = subscription handshake (no body, no
// signature); POST = leadgen delivery, HMAC-verified by meta.signed.
Route::get('webhooks/meta/leads', [MetaLeadController::class, 'verify'])
    ->withoutMiddleware('throttle:api')
    ->name('webhooks.meta.verify');
Route::post('webhooks/meta/leads', [MetaLeadController::class, 'store'])
    ->middleware('meta.signed')
    ->withoutMiddleware('throttle:api')
    ->name('webhooks.meta.store');

// Razorpay payments (PRD §6.8). Signature-verified, idempotent reconciliation.
Route::post('webhooks/razorpay', RazorpayWebhookController::class)
    ->middleware('razorpay.signed')
    ->withoutMiddleware('throttle:api')
    ->name('webhooks.razorpay');

// WhatsApp Cloud API (PRD §6.9). GET = handshake; POST = inbound, HMAC-verified.
Route::get('webhooks/whatsapp', [WhatsAppController::class, 'verify'])
    ->withoutMiddleware('throttle:api')
    ->name('webhooks.whatsapp.verify');
Route::post('webhooks/whatsapp', [WhatsAppController::class, 'store'])
    ->middleware('whatsapp.signed')
    ->withoutMiddleware('throttle:api')
    ->name('webhooks.whatsapp.store');

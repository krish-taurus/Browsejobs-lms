<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\AiUsageController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\Admin\CannedResponseController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\CodingLabController;
use App\Http\Controllers\Admin\CrmAssignmentRuleController;
use App\Http\Controllers\Admin\CrmTaskController;
use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\DunningController;
use App\Http\Controllers\Admin\FeePlanController;
use App\Http\Controllers\Admin\FunnelController;
use App\Http\Controllers\Admin\GradingController;
use App\Http\Controllers\Admin\KnowledgeController;
use App\Http\Controllers\Admin\LeadAdminController;
use App\Http\Controllers\Admin\LeadStageController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\LessonNoteController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\MessageTemplateController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\MonetizationController;
use App\Http\Controllers\Admin\PaymentLinkController;
use App\Http\Controllers\Admin\QuizController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\Admin\RiskController;
use App\Http\Controllers\Admin\RosterController;
use App\Http\Controllers\Admin\SyllabusController;
use App\Http\Controllers\Admin\TestimonialController as AdminTestimonialController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\TicketRouteController;
use App\Http\Controllers\Admin\TopicController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\CertificateVerifyController;
use App\Http\Controllers\CoachController;
use App\Http\Controllers\Courses\SyllabusDownloadController;
use App\Http\Controllers\FeeStatusController;
use App\Http\Controllers\Labs\LabController;
use App\Http\Controllers\Leads\LeadController;
use App\Http\Controllers\Me\MyAssignmentController;
use App\Http\Controllers\Me\MyCertificateController;
use App\Http\Controllers\Me\MyLessonNotesController;
use App\Http\Controllers\Me\MyQuizController;
use App\Http\Controllers\Me\MyReportController;
use App\Http\Controllers\Me\MySyllabusController;
use App\Http\Controllers\MessagePreferenceController;
use App\Http\Controllers\MyVoucherController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Reviews\ReviewController;
use App\Http\Controllers\Store\StoreController;
use App\Http\Controllers\Support\StudentTicketController;
use App\Http\Controllers\Testimonials\TestimonialController;
use App\Http\Controllers\Tutor\TutorController;
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

// Public certificate verification (PRD §6.5). NO tenant.domain — a printed QR must
// verify from any host, so the lookup is by the globally-unique code (withoutGlobalScopes).
Route::get('v1/verify/{code}', [CertificateVerifyController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('certificates.verify');

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

    // Lead-gated syllabus download (PRD §6.2 lead magnet). Public, host tenant.
    Route::post('courses/{slug}/syllabus/download', [SyllabusDownloadController::class, 'store'])
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
    Route::get('me/coach', [CoachController::class, 'show']);
    Route::get('me/fee-status', [FeeStatusController::class, 'show']);
    Route::get('me/notifications', [NotificationController::class, 'index']);
    Route::post('me/notifications/read', [NotificationController::class, 'markRead']);
    Route::get('me/message-preferences', [MessagePreferenceController::class, 'show']);
    Route::put('me/message-preferences', [MessagePreferenceController::class, 'update']);
    Route::post('me/testimonials', [TestimonialController::class, 'store']);
    Route::get('me/vouchers', [MyVoucherController::class, 'index']);
    Route::get('me/tickets', [StudentTicketController::class, 'index']);
    Route::post('me/tickets', [StudentTicketController::class, 'store']);
    Route::get('me/tickets/{ticket}', [StudentTicketController::class, 'show']);
    Route::post('me/tickets/{ticket}/reply', [StudentTicketController::class, 'reply']);
    Route::post('me/tickets/{ticket}/reopen', [StudentTicketController::class, 'reopen']);
    Route::post('me/tickets/{ticket}/csat', [StudentTicketController::class, 'csat']);
    Route::get('me/store', [StoreController::class, 'index']);
    Route::get('me/entitlements', [StoreController::class, 'entitlements']);
    Route::get('me/purchases', [StoreController::class, 'purchases']);
    Route::post('me/purchases', [StoreController::class, 'purchase']);
    Route::post('me/career-plus/subscribe', [StoreController::class, 'subscribe']);
    Route::post('me/career-plus/cancel', [StoreController::class, 'cancel']);
    Route::post('me/self-paced/{batch}/upgrade', [StoreController::class, 'upgrade']);
    Route::post('me/activity', [ActivityController::class, 'store'])->middleware('throttle:60,1');
    Route::get('me/labs', [LabController::class, 'index']);
    Route::get('me/labs/{lesson}', [LabController::class, 'show']);
    Route::post('me/labs/{lesson}/run', [LabController::class, 'run'])->middleware('throttle:ai');
    Route::post('me/labs/{lesson}/submit', [LabController::class, 'submit'])->middleware('throttle:ai');
    Route::get('me/mcq/{attempt}', [MyQuizController::class, 'show']);
    Route::post('me/mcq/{attempt}/submit', [MyQuizController::class, 'submit'])->middleware('throttle:30,1');
    Route::get('me/assignments/{lesson}', [MyAssignmentController::class, 'show']);
    Route::post('me/assignments/{lesson}/submit', [MyAssignmentController::class, 'submit'])->middleware('throttle:20,1');
    Route::get('me/grades', [MyAssignmentController::class, 'grades']);
    Route::get('me/certificates', [MyCertificateController::class, 'index']);
    Route::get('me/reports', [MyReportController::class, 'index']);
    Route::get('me/reports/{report}', [MyReportController::class, 'show']);
    Route::get('me/lessons/{lesson}/notes', [MyLessonNotesController::class, 'show']);
    Route::get('me/courses/{course}/syllabus', [MySyllabusController::class, 'show']);
    Route::get('me/tutor', [TutorController::class, 'index']);
    Route::get('me/tutor/{conversation}', [TutorController::class, 'show']);
    Route::post('me/tutor', [TutorController::class, 'store'])->middleware('throttle:ai');
    Route::post('me/tutor/labs/{lesson}', [TutorController::class, 'askLab'])->middleware('throttle:ai');
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
        Route::get('lessons/{lesson}/coding-lab', [CodingLabController::class, 'show']);
        Route::put('lessons/{lesson}/coding-lab', [CodingLabController::class, 'upsert']);
        Route::get('quiz-lessons', [QuizController::class, 'index']);
        Route::get('lessons/{lesson}/quiz', [QuizController::class, 'show']);
        Route::put('lessons/{lesson}/quiz', [QuizController::class, 'upsert']);
        Route::post('lessons/{lesson}/quiz/generate', [QuizController::class, 'generate']);
        Route::post('quizzes/{quiz}/questions', [QuizController::class, 'storeQuestion']);
        Route::patch('quiz-questions/{question}', [QuizController::class, 'updateQuestion']);
        Route::delete('quiz-questions/{question}', [QuizController::class, 'destroyQuestion']);
        Route::post('quizzes/{quiz}/approve', [QuizController::class, 'approve']);
        Route::get('note-lessons', [LessonNoteController::class, 'index']);
        Route::get('lessons/{lesson}/notes', [LessonNoteController::class, 'show']);
        Route::put('lessons/{lesson}/notes', [LessonNoteController::class, 'upsert']);
        Route::post('lessons/{lesson}/notes/upload', [LessonNoteController::class, 'upload']);
        Route::post('lessons/{lesson}/notes/generate', [LessonNoteController::class, 'generate']);
        Route::patch('lessons/{lesson}/notes', [LessonNoteController::class, 'updateNotes']);
        Route::post('lesson-notes/{note}/approve', [LessonNoteController::class, 'approve']);
        Route::get('course-syllabuses', [SyllabusController::class, 'index']);
        Route::get('courses/{course}/syllabus', [SyllabusController::class, 'show']);
        Route::post('courses/{course}/syllabus/generate', [SyllabusController::class, 'generate']);
        Route::patch('courses/{course}/syllabus', [SyllabusController::class, 'update']);
        Route::post('syllabuses/{syllabus}/approve', [SyllabusController::class, 'approve']);
        Route::get('assignment-lessons', [AssignmentController::class, 'index']);
        Route::get('lessons/{lesson}/assignment', [AssignmentController::class, 'show']);
        Route::put('lessons/{lesson}/assignment', [AssignmentController::class, 'upsert']);
        Route::get('assignment-submissions', [GradingController::class, 'index']);
        Route::get('submissions/{submission}', [GradingController::class, 'show']);
        Route::patch('grades/{grade}', [GradingController::class, 'updateGrade']);
        Route::post('grades/{grade}/release', [GradingController::class, 'release']);
        Route::get('knowledge', [KnowledgeController::class, 'index']);
        Route::post('knowledge', [KnowledgeController::class, 'store']);
        Route::patch('knowledge/{knowledge}', [KnowledgeController::class, 'update']);
        Route::delete('knowledge/{knowledge}', [KnowledgeController::class, 'destroy']);
        Route::post('knowledge/reindex', [KnowledgeController::class, 'reindex']);
    });

    Route::middleware('can:manage-batches')->group(function () {
        Route::get('batches', [BatchController::class, 'index']);
        Route::post('batches', [BatchController::class, 'store']);
        Route::get('batches/{batch}', [BatchController::class, 'show']);
    });

    Route::middleware('can:manage-rosters')->group(function () {
        Route::get('certificates', [CertificateController::class, 'index']);
        Route::post('certificates', [CertificateController::class, 'store']);
        Route::post('certificates/{certificate}/revoke', [CertificateController::class, 'revoke']);
        Route::post('batches/{batch}/members', [RosterController::class, 'store']);
        Route::post('batches/{batch}/import', [RosterController::class, 'import']);
        Route::post('batches/{batch}/complete', [RosterController::class, 'completeBootcamp']);
        Route::post('members/{member}/transfer', [RosterController::class, 'transfer']);
        Route::post('members/{member}/remove', [RosterController::class, 'remove']);
        Route::post('members/{member}/convert', [RosterController::class, 'convert']);
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

        Route::get('funnel', [FunnelController::class, 'index']);
        Route::get('risk', [RiskController::class, 'index']);
    });

    // Payments + EMI (PRD §6.8).
    Route::middleware('can:manage-fees')->group(function () {
        Route::get('fee-plans', [FeePlanController::class, 'index']);
        Route::get('fee-plans/batches', [FeePlanController::class, 'batches']);
        Route::get('fee-plans/candidates', [FeePlanController::class, 'candidates']);
        Route::get('fee-plans/voucher', [FeePlanController::class, 'voucher']);
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

    // Review-for-Voucher engine (PRD §5 Stage 3 / §6.8).
    Route::middleware('can:manage-vouchers')->group(function () {
        Route::get('vouchers', [VoucherController::class, 'index']);
        Route::get('vouchers/analytics', [VoucherController::class, 'analytics']);
        Route::post('vouchers', [VoucherController::class, 'store']);
        Route::put('vouchers/{voucher}', [VoucherController::class, 'update']);
        Route::delete('vouchers/{voucher}', [VoucherController::class, 'destroy']);

        Route::get('testimonials', [AdminTestimonialController::class, 'index']);
        Route::post('testimonials/{testimonial}/approve', [AdminTestimonialController::class, 'approve']);
        Route::post('testimonials/{testimonial}/reject', [AdminTestimonialController::class, 'reject']);
    });

    // Student Support Desk (PRD §6.13).
    Route::middleware('can:handle-tickets')->group(function () {
        Route::get('tickets', [TicketController::class, 'index']);
        Route::get('tickets/staff', [TicketController::class, 'staff']);
        Route::get('tickets/{ticket}', [TicketController::class, 'show']);
        Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply']);
        Route::post('tickets/{ticket}/status', [TicketController::class, 'status']);
        Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign']);
        Route::post('tickets/{ticket}/escalate', [TicketController::class, 'escalate']);

        Route::get('canned-responses', [CannedResponseController::class, 'index']);
        Route::post('canned-responses', [CannedResponseController::class, 'store']);
        Route::put('canned-responses/{cannedResponse}', [CannedResponseController::class, 'update']);
        Route::delete('canned-responses/{cannedResponse}', [CannedResponseController::class, 'destroy']);

        Route::get('ticket-routes', [TicketRouteController::class, 'index']);
        Route::put('ticket-routes/{ticketRoute}', [TicketRouteController::class, 'update']);
    });

    // Entitlement engine + monetization (PRD §6.17 / §6.3).
    Route::middleware('can:manage-monetization')->group(function () {
        Route::get('monetization-settings', [MonetizationController::class, 'settings']);
        Route::put('monetization-settings', [MonetizationController::class, 'updateSettings']);
        Route::get('products', [MonetizationController::class, 'products']);
        Route::post('products', [MonetizationController::class, 'storeProduct']);
        Route::put('products/{product}', [MonetizationController::class, 'updateProduct']);
        Route::delete('products/{product}', [MonetizationController::class, 'destroyProduct']);
        Route::post('batches/{batch}/publish-self-paced', [MonetizationController::class, 'publishSelfPaced']);
        Route::post('purchases/{purchase}/refund', [MonetizationController::class, 'refund']);
        Route::get('revenue', [RevenueController::class, 'index']);
        Route::get('revenue/purchases', [RevenueController::class, 'purchases']);
        Route::get('ai-usage', [AiUsageController::class, 'index']);
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

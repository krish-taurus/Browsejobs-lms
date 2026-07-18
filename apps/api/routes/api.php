<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\AiUsageController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\Admin\BatchDispatchController;
use App\Http\Controllers\Admin\CannedResponseController;
use App\Http\Controllers\Admin\CareAdminController;
use App\Http\Controllers\Admin\CelebrationController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\CodingLabController;
use App\Http\Controllers\Admin\ContentHubController;
use App\Http\Controllers\Admin\CrmAssignmentRuleController;
use App\Http\Controllers\Admin\CrmTaskController;
use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\CvApprovalController;
use App\Http\Controllers\Admin\DunningController;
use App\Http\Controllers\Admin\FeePlanController;
use App\Http\Controllers\Admin\FunnelController;
use App\Http\Controllers\Admin\GradingController;
use App\Http\Controllers\Admin\InterviewBankController;
use App\Http\Controllers\Admin\KnowledgeController;
use App\Http\Controllers\Admin\LeadAdminController;
use App\Http\Controllers\Admin\LeadStageController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\LessonNoteController;
use App\Http\Controllers\Admin\LiveSessionController;
use App\Http\Controllers\Admin\MarketJdController;
use App\Http\Controllers\Admin\MentorAdminController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\MessageTemplateController;
use App\Http\Controllers\Admin\MockBlueprintController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\MonetizationController;
use App\Http\Controllers\Admin\PaymentLinkController;
use App\Http\Controllers\Admin\PlacementAdminController;
use App\Http\Controllers\Admin\PointsSettingController;
use App\Http\Controllers\Admin\PulseAdminController;
use App\Http\Controllers\Admin\QuizController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\Admin\RiskController;
use App\Http\Controllers\Admin\RosterController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SupportDocumentController;
use App\Http\Controllers\Admin\SyllabusController;
use App\Http\Controllers\Admin\SyllabusRecommendationController;
use App\Http\Controllers\Admin\TestimonialController as AdminTestimonialController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\TicketRouteController;
use App\Http\Controllers\Admin\TopicController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Admin\ZoomLicenseController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\Care\CareController;
use App\Http\Controllers\CertificateVerifyController;
use App\Http\Controllers\CoachController;
use App\Http\Controllers\Courses\SyllabusDownloadController;
use App\Http\Controllers\Cv\CvController;
use App\Http\Controllers\FeeStatusController;
use App\Http\Controllers\Labs\LabController;
use App\Http\Controllers\Leads\LeadController;
use App\Http\Controllers\Me\BoosterController;
use App\Http\Controllers\Me\LeaderboardController;
use App\Http\Controllers\Me\MyAssignmentController;
use App\Http\Controllers\Me\MyCertificateController;
use App\Http\Controllers\Me\MyClassController;
use App\Http\Controllers\Me\MyLessonNotesController;
use App\Http\Controllers\Me\MyQuizController;
use App\Http\Controllers\Me\MyRecordingController;
use App\Http\Controllers\Me\MyReportController;
use App\Http\Controllers\Me\MySyllabusController;
use App\Http\Controllers\Me\PulsePageController;
use App\Http\Controllers\Mentoring\MentorBookingController;
use App\Http\Controllers\Mentoring\MentorHubController;
use App\Http\Controllers\MessagePreferenceController;
use App\Http\Controllers\Mocks\MockController;
use App\Http\Controllers\MyVoucherController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Placement\PlacementController;
use App\Http\Controllers\Placement\ProofController;
use App\Http\Controllers\Reviews\ReviewController;
use App\Http\Controllers\Store\StoreController;
use App\Http\Controllers\Support\StudentTicketController;
use App\Http\Controllers\Testimonials\TestimonialController;
use App\Http\Controllers\Tutor\TutorController;
use App\Http\Controllers\Webhooks\MetaLeadController;
use App\Http\Controllers\Webhooks\RazorpayWebhookController;
use App\Http\Controllers\Webhooks\VoiceWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppController;
use App\Http\Controllers\Webhooks\ZoomWebhookController;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

/*
| API routes. The `api` group (throttle:api + Sanctum stateful) is applied
| automatically. Auth uses the Sanctum SPA cookie flow (ADR 0004).
*/

// Public shared CV (PRD §6.7). NO tenant.domain — the share token is the key.
Route::get('v1/cv/shared/{token}', [CvController::class, 'shared'])
    ->middleware('throttle:30,1')
    ->name('cv.shared');

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

    // Proof Engine aggregates (PRD §6.11) — anonymised, disclaimered.
    Route::get('proof', ProofController::class)->middleware('throttle:30,1');

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
    Route::get('me/leaderboard', [LeaderboardController::class, 'show']);
    Route::put('me/leaderboard', [LeaderboardController::class, 'updatePreference']);
    Route::get('me/pulse', [PulsePageController::class, 'show']);
    Route::post('me/pulse/celebrations/{celebration}/guidance', [PulsePageController::class, 'guidance'])->middleware('throttle:ai');
    Route::post('me/pulse/content/{item}/viewed', [PulsePageController::class, 'contentViewed'])->middleware('throttle:60,1');
    Route::get('me/fee-status', [FeeStatusController::class, 'show']);
    Route::get('me/notifications', [NotificationController::class, 'index']);
    Route::post('me/notifications/read', [NotificationController::class, 'markRead']);
    Route::get('me/message-preferences', [MessagePreferenceController::class, 'show']);
    Route::put('me/message-preferences', [MessagePreferenceController::class, 'update']);
    Route::post('me/testimonials', [TestimonialController::class, 'store']);
    Route::get('me/vouchers', [MyVoucherController::class, 'index']);

    // Live classes + recordings (PRD §6.3). Join and download are fee/enrolment gated.
    Route::get('me/classes', [MyClassController::class, 'index']);
    Route::post('me/classes/{session}/join', [MyClassController::class, 'join']);
    Route::get('me/recordings', [MyRecordingController::class, 'index']);
    Route::get('me/recordings/{recording}/download', [MyRecordingController::class, 'download']);

    Route::get('me/tickets', [StudentTicketController::class, 'index']);
    Route::post('me/tickets', [StudentTicketController::class, 'store']);
    // AI first-response (PRD §6.13) — declared before `me/tickets/{ticket}` so the
    // literal segment is not swallowed by the parameter, and throttled as an AI endpoint.
    Route::post('me/tickets/deflect', [StudentTicketController::class, 'deflect'])->middleware('throttle:ai');
    Route::post('me/tickets/deflect/{deflection}/accept', [StudentTicketController::class, 'acceptDeflection']);
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
    // Review protection & retention (PRD §6.20).
    Route::get('me/checkin', [CareController::class, 'index']);
    Route::post('me/nps/{pulse}', [CareController::class, 'respond'])->middleware('throttle:10,1');
    Route::post('me/pause', [CareController::class, 'requestPause'])->middleware('throttle:5,1');
    // Placement pipeline (PRD §6.11).
    Route::get('me/placement', [PlacementController::class, 'index']);
    Route::post('me/applications', [PlacementController::class, 'store'])->middleware('throttle:20,1');
    Route::post('me/applications/{application}/rounds', [PlacementController::class, 'storeRound'])->middleware('throttle:20,1');
    Route::post('me/applications/{application}/withdraw', [PlacementController::class, 'withdraw']);

    // Career+ job-probability boosters (PRD §6.20, P4.6b). Status is open (upsell);
    // generation is gated by an active Career+ subscription.
    Route::get('me/boosters', [BoosterController::class, 'index']);
    Route::middleware('career-plus')->group(function () {
        Route::post('me/boosters/linkedin', [BoosterController::class, 'linkedin'])->middleware('throttle:ai');
        Route::post('me/boosters/github', [BoosterController::class, 'github'])->middleware('throttle:ai');
        Route::post('me/boosters/prep-pack', [BoosterController::class, 'prepPack'])->middleware('throttle:ai');
        Route::post('me/applications/{application}/tailor', [PlacementController::class, 'tailor'])->middleware('throttle:ai');
    });

    // AI CV suite (PRD §6.7).
    Route::get('me/cv', [CvController::class, 'index']);
    Route::post('me/cv', [CvController::class, 'store'])->middleware('throttle:ai');
    Route::get('me/cv/profile', [CvController::class, 'profile']);
    Route::put('me/cv/profile', [CvController::class, 'updateProfile']);
    Route::post('me/cv/profile/import', [CvController::class, 'importCv'])->middleware('throttle:ai');
    Route::get('me/cv/{cv}/ats-text', [CvController::class, 'atsText']);
    Route::get('me/cv/{cv}', [CvController::class, 'show']);
    Route::patch('me/cv/{cv}', [CvController::class, 'update']);
    Route::post('me/cv/{cv}/ats', [CvController::class, 'atsCheck'])->middleware('throttle:30,1');
    Route::post('me/cv/{cv}/share', [CvController::class, 'share']);
    Route::delete('me/cv/{cv}/share', [CvController::class, 'unshare']);
    // Mentor scheduling (PRD §6.11).
    Route::get('me/mentors', [MentorBookingController::class, 'index']);
    Route::get('me/mentor-sessions', [MentorBookingController::class, 'sessions']);
    Route::post('me/mentor-sessions', [MentorBookingController::class, 'store'])->middleware('throttle:20,1');
    Route::post('me/mentor-sessions/{session}/cancel', [MentorBookingController::class, 'destroy']);
    Route::post('me/mentor-sessions/{session}/reschedule', [MentorBookingController::class, 'move']);
    Route::post('me/mentor-sessions/{session}/rate', [MentorBookingController::class, 'rate']);
    Route::get('me/mentor-sessions/{session}/ics', [MentorBookingController::class, 'ics']);
    // The mentor's own workspace (ownership-gated, no extra permission).
    Route::get('me/mentorhub', [MentorHubController::class, 'index']);
    Route::post('me/mentorhub/{session}/feedback', [MentorHubController::class, 'submitFeedback']);
    Route::post('me/mentorhub/{session}/no-show', [MentorHubController::class, 'markNoShow']);
    Route::get('me/mentorhub/availability', [MentorHubController::class, 'availability']);
    Route::put('me/mentorhub/availability', [MentorHubController::class, 'updateAvailability']);
    Route::get('me/mocks', [MockController::class, 'index']);
    Route::post('me/mocks', [MockController::class, 'store']);
    Route::post('me/mocks/voice', [MockController::class, 'storeVoice'])->middleware('throttle:10,1');
    Route::get('me/mocks/{mock}', [MockController::class, 'show']);
    Route::post('me/mocks/{mock}/answer', [MockController::class, 'answer'])->middleware('throttle:ai');
    Route::post('me/mocks/{mock}/finish', [MockController::class, 'finish'])->middleware('throttle:ai');
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

        // Curriculum Intelligence — syllabus recommendation reports (PRD §6.21).
        Route::get('syllabus-recommendations', [SyllabusRecommendationController::class, 'index']);
        Route::post('courses/{course}/syllabus-recommendations', [SyllabusRecommendationController::class, 'generate'])->middleware('throttle:ai');
        Route::post('syllabus-recommendations/{recommendation}/approve', [SyllabusRecommendationController::class, 'approve']);
        Route::post('syllabus-recommendations/{recommendation}/reject', [SyllabusRecommendationController::class, 'reject']);
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
        Route::get('mock-blueprints', [MockBlueprintController::class, 'index']);
        Route::post('mock-blueprints', [MockBlueprintController::class, 'store']);
        Route::patch('mock-blueprints/{blueprint}', [MockBlueprintController::class, 'update']);
        Route::delete('mock-blueprints/{blueprint}', [MockBlueprintController::class, 'destroy']);
    });

    // Real Interview Intelligence (PRD §6.6) — placement team only.
    Route::middleware('can:manage-placements')->group(function () {
        Route::get('placements', [PlacementAdminController::class, 'index']);
        Route::post('jobs', [PlacementAdminController::class, 'storeJob']);
        Route::patch('jobs/{job}', [PlacementAdminController::class, 'updateJob']);
        Route::patch('applications/{application}', [PlacementAdminController::class, 'updateApplication']);
        Route::get('cvs', [CvApprovalController::class, 'index']);
        Route::post('cvs/{cv}/approve', [CvApprovalController::class, 'approve']);
        Route::get('mentors', [MentorAdminController::class, 'index']);
        Route::post('mentors', [MentorAdminController::class, 'store']);
        Route::patch('mentors/{mentor}', [MentorAdminController::class, 'update']);
        Route::get('interview-transcripts', [InterviewBankController::class, 'transcripts']);
        Route::post('interview-transcripts', [InterviewBankController::class, 'upload'])->middleware('throttle:30,1');
        Route::get('interview-bank', [InterviewBankController::class, 'index']);
        Route::post('interview-bank/approve-batch', [InterviewBankController::class, 'approveBatch']);
        Route::post('interview-bank/{question}/approve', [InterviewBankController::class, 'approve']);
        Route::post('interview-bank/{question}/reject', [InterviewBankController::class, 'reject']);

        // Curriculum Intelligence — job-market JD ingestion + demand trends (PRD §6.21).
        Route::get('market-jds', [MarketJdController::class, 'index']);
        Route::post('market-jds', [MarketJdController::class, 'store']);
        Route::post('market-jds/import', [MarketJdController::class, 'import'])->middleware('throttle:30,1');
    });

    Route::middleware('can:manage-batches')->group(function () {
        Route::get('batches', [BatchController::class, 'index']);
        Route::post('batches', [BatchController::class, 'store']);
        Route::get('batches/{batch}', [BatchController::class, 'show']);

        // Zoom host-license pool (PRD §6.3) — allocate a license per mentor.
        Route::get('zoom-licenses', [ZoomLicenseController::class, 'index']);
        Route::post('zoom-licenses', [ZoomLicenseController::class, 'store']);
        Route::put('zoom-licenses/{zoomLicense}', [ZoomLicenseController::class, 'update']);
        Route::delete('zoom-licenses/{zoomLicense}', [ZoomLicenseController::class, 'destroy']);
    });

    // Platform integration settings (PRD §6.14) — super-admin only.
    Route::middleware('can:manage-settings')->group(function () {
        Route::get('settings', [SettingsController::class, 'index']);
        Route::put('settings', [SettingsController::class, 'update']);
    });

    // Live-class scheduling (PRD §6.3). Reschedule/cancel auto-notify the batch.
    Route::middleware('can:teach-classes')->group(function () {
        Route::get('batches/{batch}/sessions', [LiveSessionController::class, 'index']);
        Route::post('batches/{batch}/sessions', [LiveSessionController::class, 'store']);
        Route::post('sessions/{session}/reschedule', [LiveSessionController::class, 'reschedule']);
        Route::post('sessions/{session}/cancel', [LiveSessionController::class, 'cancel']);

        // Send a specific mock / assignment to a batch (PRD §6.5/§6.6).
        Route::get('batches/{batch}/dispatch-options', [BatchDispatchController::class, 'options']);
        Route::post('batches/{batch}/dispatch-mock', [BatchDispatchController::class, 'sendMock']);
        Route::post('batches/{batch}/dispatch-assignment', [BatchDispatchController::class, 'sendAssignment']);
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

        // Candidates directory + reallocation (student-first view).
        Route::get('students', [StudentController::class, 'index']);
        Route::post('students/{student}/enrollments', [StudentController::class, 'enroll']);
    });

    // Built-in CRM (PRD §6.12).
    Route::middleware('can:manage-leads')->group(function () {
        // Review protection & retention (PRD §6.20) — counselor care desk.
        Route::get('care', [CareAdminController::class, 'index']);
        Route::post('care/alerts/{alert}/handle', [CareAdminController::class, 'handleAlert']);
        Route::post('care/pauses/{pause}/decide', [CareAdminController::class, 'decidePause']);
        Route::patch('care/onboarding/{checklist}', [CareAdminController::class, 'updateOnboarding']);
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

        // P3.7 engagement (PRD §6.18/§6.19): celebrations, Market Pulse, Content Hub.
        Route::get('celebrations', [CelebrationController::class, 'index']);
        Route::post('celebrations', [CelebrationController::class, 'store']);
        Route::post('celebrations/{celebration}/publish', [CelebrationController::class, 'publish']);
        Route::delete('celebrations/{celebration}', [CelebrationController::class, 'destroy']);
        Route::get('pulse', [PulseAdminController::class, 'index']);
        Route::post('pulse/items', [PulseAdminController::class, 'storeItem']);
        Route::delete('pulse/items/{item}', [PulseAdminController::class, 'destroyItem']);
        Route::post('pulse/generate', [PulseAdminController::class, 'generate'])->middleware('throttle:ai');
        Route::get('content-hub', [ContentHubController::class, 'index']);
        Route::post('content-hub', [ContentHubController::class, 'store']);
        Route::delete('content-hub/{item}', [ContentHubController::class, 'destroy']);
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
        // AI reply draft (PRD §6.13) — throttled as an AI endpoint; drafts, never sends.
        Route::post('tickets/{ticket}/draft-reply', [TicketController::class, 'draftReply'])->middleware('throttle:ai');
        Route::post('tickets/{ticket}/clear-ai-priority', [TicketController::class, 'clearAiPriority']);

        // The support policy corpus AI deflection answers from (PRD §6.13).
        Route::get('support-documents', [SupportDocumentController::class, 'index']);
        Route::post('support-documents', [SupportDocumentController::class, 'store']);
        Route::put('support-documents/{document}', [SupportDocumentController::class, 'update']);
        Route::delete('support-documents/{document}', [SupportDocumentController::class, 'destroy']);

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
        Route::get('points-settings', [PointsSettingController::class, 'show']);
        Route::put('points-settings', [PointsSettingController::class, 'update']);
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

// Voice mock provider (PRD §6.6, P4.3). Secret-verified end-of-call reports.
Route::post('webhooks/voice', VoiceWebhookController::class)
    ->middleware('voice.signed')
    ->withoutMiddleware('throttle:api')
    ->name('webhooks.voice');

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

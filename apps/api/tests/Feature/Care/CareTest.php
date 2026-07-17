<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Models\ActivityEvent;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\EnrolmentPause;
use App\Models\FeePlan;
use App\Models\InAppNotification;
use App\Models\InterventionAlert;
use App\Models\Module;
use App\Models\NpsPulse;
use App\Models\OnboardingChecklist;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\TopicCompletion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->seed(RolePermissionSeeder::class);
    $this->counselor = counselorIn($this->tenant);
});

/**
 * An enrolled student, optionally enrolled $daysAgo with $topics topics of
 * which $done are completed.
 *
 * @return array{student: User, member: BatchMember, course: Course}
 */
function careStudent(Tenant $tenant, int $daysAgo = 8, int $topics = 0, int $done = 0): array
{
    $course = Course::factory()->for($tenant)->create();
    $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id, 'type' => 'paid']);
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    $member = BatchMember::factory()->for($tenant)->create([
        'batch_id' => $batch->id, 'user_id' => $student->id, 'status' => 'enrolled',
        'created_at' => now()->subDays($daysAgo),
    ]);

    if ($topics > 0) {
        withinTenant($tenant, function () use ($tenant, $course, $student, $topics, $done): void {
            $module = Module::factory()->create(['tenant_id' => $tenant->id, 'course_id' => $course->id]);
            for ($i = 0; $i < $topics; $i++) {
                $topic = Topic::factory()->create(['tenant_id' => $tenant->id, 'module_id' => $module->id]);
                if ($i < $done) {
                    TopicCompletion::query()->create([
                        'tenant_id' => $tenant->id, 'user_id' => $student->id,
                        'topic_id' => $topic->id, 'completed_at' => now(),
                    ]);
                }
            }
        });
    }

    return ['student' => $student, 'member' => $member, 'course' => $course];
}

/* ---- care:dispatch ---- */

it('opens the week-1 pulse and onboarding checklist once, with counselor and student nudges', function () {
    ['student' => $student] = careStudent($this->tenant, daysAgo: 8);

    artisan('care:dispatch')->assertSuccessful();
    artisan('care:dispatch')->assertSuccessful(); // idempotent

    expect(NpsPulse::withoutGlobalScopes()->where('user_id', $student->id)->where('milestone', 'week1')->count())->toBe(1)
        ->and(OnboardingChecklist::withoutGlobalScopes()->where('user_id', $student->id)->count())->toBe(1)
        ->and(InAppNotification::withoutGlobalScopes()->where('user_id', $student->id)->where('url', '/checkin')->count())->toBe(1)
        ->and(InAppNotification::withoutGlobalScopes()->where('user_id', $this->counselor->id)->where('title', 'like', 'Welcome call due%')->count())->toBe(1);
});

it('escalates the milestone with course progress: mid at 50%, pre-placement at 80%', function () {
    ['student' => $mid] = careStudent($this->tenant, daysAgo: 20, topics: 4, done: 2);
    ['student' => $pre] = careStudent($this->tenant, daysAgo: 20, topics: 5, done: 4);

    artisan('care:dispatch')->assertSuccessful();

    expect(NpsPulse::withoutGlobalScopes()->where('user_id', $mid->id)->value('milestone'))->toBe('mid')
        ->and(NpsPulse::withoutGlobalScopes()->where('user_id', $pre->id)->value('milestone'))->toBe('pre_placement');
});

/* ---- NPS routing ---- */

it('routes a promoter to the unconditioned Google review invitation', function () {
    config(['retention.nps.google_review_url' => 'https://g.page/r/browsejobs/review']);
    ['student' => $student] = careStudent($this->tenant);
    $pulse = NpsPulse::query()->create(['tenant_id' => $this->tenant->id, 'user_id' => $student->id, 'milestone' => 'week1']);
    Sanctum::actingAs($student);

    postJson("/api/v1/me/nps/{$pulse->id}", ['score' => 10])
        ->assertOk()
        ->assertJsonPath('data.routed', 'google_review')
        ->assertJsonPath('data.review_url', 'https://g.page/r/browsejobs/review');

    // Answered pulses cannot be answered again.
    postJson("/api/v1/me/nps/{$pulse->id}", ['score' => 2])->assertNotFound();
});

it('keeps passives quiet and fires instant counselor rescue for detractors', function () {
    ['student' => $student] = careStudent($this->tenant);
    $passive = NpsPulse::query()->create(['tenant_id' => $this->tenant->id, 'user_id' => $student->id, 'milestone' => 'week1']);
    $sad = NpsPulse::query()->create(['tenant_id' => $this->tenant->id, 'user_id' => $student->id, 'milestone' => 'mid']);
    Sanctum::actingAs($student);

    postJson("/api/v1/me/nps/{$passive->id}", ['score' => 8])->assertOk()
        ->assertJsonPath('data.routed', 'none');
    expect(InAppNotification::withoutGlobalScopes()->where('user_id', $this->counselor->id)->count())->toBe(0);

    postJson("/api/v1/me/nps/{$sad->id}", ['score' => 3, 'comment' => 'Classes keep getting rescheduled.'])
        ->assertOk()->assertJsonPath('data.routed', 'rescue');
    expect(InAppNotification::withoutGlobalScopes()
        ->where('user_id', $this->counselor->id)
        ->where('title', 'like', 'Detractor rescue%')
        ->count())->toBe(1);
});

/* ---- Pause / defer ---- */

it('takes a pause request once, telling the counselors', function () {
    ['student' => $student] = careStudent($this->tenant);
    Sanctum::actingAs($student);

    postJson('/api/v1/me/pause', ['reason' => 'Family emergency — need six weeks away.'])
        ->assertCreated();
    postJson('/api/v1/me/pause', ['reason' => 'Second request should be blocked.'])
        ->assertUnprocessable();

    expect(InAppNotification::withoutGlobalScopes()
        ->where('user_id', $this->counselor->id)
        ->where('title', 'like', 'Pause request%')->count())->toBe(1);

    $outsider = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($outsider);
    postJson('/api/v1/me/pause', ['reason' => 'I am not even enrolled anywhere.'])->assertUnprocessable();
});

it('approving a pause freezes the seat, audits, and respects the max window', function () {
    ['student' => $student, 'member' => $member] = careStudent($this->tenant);
    $pause = EnrolmentPause::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $student->id,
        'batch_member_id' => $member->id, 'reason' => 'Health break needed for recovery.',
    ]);
    Sanctum::actingAs($this->counselor);

    // Beyond the max pause window → refused.
    postJson("/api/v1/admin/care/pauses/{$pause->id}/decide", [
        'decision' => 'approved', 'paused_until' => now()->addDays(200)->toDateString(),
    ])->assertUnprocessable();

    postJson("/api/v1/admin/care/pauses/{$pause->id}/decide", [
        'decision' => 'approved', 'paused_until' => now()->addDays(45)->toDateString(),
    ])->assertOk()->assertJsonPath('data.status', 'approved');

    expect($member->fresh()->status->value)->toBe('paused')
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'enrolment_pause.approved')->count())->toBe(1)
        ->and(InAppNotification::withoutGlobalScopes()->where('user_id', $student->id)->where('title', 'like', '%approved%')->count())->toBe(1);

    // Already decided → refused.
    postJson("/api/v1/admin/care/pauses/{$pause->id}/decide", ['decision' => 'rejected'])->assertUnprocessable();
});

it('rejecting a pause leaves the enrolment untouched', function () {
    ['student' => $student, 'member' => $member] = careStudent($this->tenant);
    $pause = EnrolmentPause::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $student->id,
        'batch_member_id' => $member->id, 'reason' => 'Thinking about a break.',
    ]);
    Sanctum::actingAs($this->counselor);

    postJson("/api/v1/admin/care/pauses/{$pause->id}/decide", ['decision' => 'rejected'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');

    expect($member->fresh()->status->value)->toBe('enrolled');
});

/* ---- Rage signals ---- */

it('opens one intervention alert for repeated failed payments, with cooldown', function () {
    ['student' => $student] = careStudent($this->tenant);
    $plan = FeePlan::factory()->for($this->tenant)->create(['user_id' => $student->id]);
    Payment::factory()->for($this->tenant)->count(2)->create([
        'fee_plan_id' => $plan->id, 'status' => PaymentStatus::Failed->value,
    ]);

    artisan('care:signals')->assertSuccessful();
    artisan('care:signals')->assertSuccessful(); // cooldown — no duplicate

    $alert = InterventionAlert::withoutGlobalScopes()->sole();
    expect($alert->signals)->toContain('repeated failed payments')
        ->and(InAppNotification::withoutGlobalScopes()
            ->where('user_id', $this->counselor->id)
            ->where('title', 'like', 'Priority intervention%')->count())->toBe(1);
});

it('detects an engagement cliff: active before, silent now', function () {
    ['student' => $student] = careStudent($this->tenant);
    withinTenant($this->tenant, fn () => ActivityEvent::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $student->id,
        'type' => 'lesson_viewed', 'occurred_at' => now()->subDays(12),
    ]));

    artisan('care:signals')->assertSuccessful();

    expect(InterventionAlert::withoutGlobalScopes()->sole()->signals)->toContain('engagement cliff');
});

it('lets a counselor handle an alert with a resolution note', function () {
    ['student' => $student] = careStudent($this->tenant);
    $alert = InterventionAlert::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $student->id, 'signals' => ['engagement cliff'],
    ]);
    Sanctum::actingAs($this->counselor);

    postJson("/api/v1/admin/care/alerts/{$alert->id}/handle", ['resolution' => 'Called — exam week, back on Monday.'])
        ->assertOk()->assertJsonPath('data.status', 'handled');

    expect($alert->fresh()->handled_by)->toBe($this->counselor->id);
});

/* ---- Access + onboarding tracker ---- */

it('locks the care desk to counselors and tracks onboarding steps', function () {
    ['student' => $student] = careStudent($this->tenant);
    $checklist = OnboardingChecklist::query()->create(['tenant_id' => $this->tenant->id, 'user_id' => $student->id]);

    Sanctum::actingAs($student);
    getJson('/api/v1/admin/care')->assertForbidden();

    Sanctum::actingAs($this->counselor);
    getJson('/api/v1/admin/care')->assertOk()
        ->assertJsonPath('data.onboarding.0.student', $student->name);

    patchJson("/api/v1/admin/care/onboarding/{$checklist->id}", ['step' => 'welcome_call'])->assertOk();
    expect($checklist->fresh()->welcome_call_at)->not->toBeNull()
        ->and($checklist->fresh()->assigned_to)->toBe($this->counselor->id);
});

it('shows the student their pending pulses and pause state on the check-in page', function () {
    ['student' => $student] = careStudent($this->tenant);
    NpsPulse::query()->create(['tenant_id' => $this->tenant->id, 'user_id' => $student->id, 'milestone' => 'week1']);
    Sanctum::actingAs($student);

    getJson('/api/v1/me/checkin')->assertOk()
        ->assertJsonPath('data.pending_pulses.0.milestone', 'week1')
        ->assertJsonPath('data.can_request_pause', true);
});

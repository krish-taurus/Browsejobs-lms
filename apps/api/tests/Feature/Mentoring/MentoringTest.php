<?php

declare(strict_types=1);

use App\Actions\Mentoring\MarkMentorNoShow;
use App\Actions\Mentoring\SubmitMentorFeedback;
use App\Enums\EntitlementFeature;
use App\Jobs\SendMentorReminder;
use App\Models\ActivityEvent;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\MentorAvailability;
use App\Models\MentorAvailabilityException;
use App\Models\MentorProfile;
use App\Models\MentorSession;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Mentoring\SlotFinder;
use App\Support\Messaging\Messenger;
use App\Support\Scoring\ScoreCalculator;
use App\Support\Zoom\FakeZoomClient;
use App\Support\Zoom\ZoomClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    // Monday 09:00 IST (03:30 UTC) — all slot math in these tests hangs off this.
    Carbon::setTestNow('2026-07-20 03:30:00');

    $this->zoom = new FakeZoomClient;
    app()->instance(ZoomClient::class, $this->zoom);
    $this->tenant = Tenant::factory()->create();

    foreach (['mentor_booked', 'mentor_reminder', 'mentor_cancelled', 'mentor_rescheduled'] as $key) {
        MessageTemplate::factory()->for($this->tenant)->create([
            'key' => $key, 'channel' => 'whatsapp', 'body' => '{{name}} {{with}} {{when}} {{link}}',
        ]);
    }
});

/**
 * A mentor available Tue+Wed 10:00–12:00 IST, and a student with credits.
 *
 * @return array{mentor: MentorProfile, student: User}
 */
function mentoringSetup(Tenant $tenant, int $credits = 1): array
{
    $mentorUser = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $mentor = MentorProfile::factory()->for($tenant)->create(['user_id' => $mentorUser->id]);
    foreach ([2, 3] as $weekday) { // Tue, Wed
        MentorAvailability::query()->create([
            'tenant_id' => $tenant->id, 'mentor_profile_id' => $mentor->id,
            'weekday' => $weekday, 'start_minute' => 600, 'end_minute' => 720,
        ]);
    }

    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    if ($credits > 0) {
        app(EntitlementService::class)->grantCredits($student, EntitlementFeature::Mentor->value, $credits, 'test');
    }

    return ['mentor' => $mentor, 'student' => $student];
}

/** Tue 21 Jul 2026, 10:00 IST as UTC ISO. */
function tueTenIst(): string
{
    return '2026-07-21T04:30:00+00:00';
}

/* ---- Slots ---- */

it('cuts IST slots from weekly windows minus exceptions, booked sessions, and the notice lead', function () {
    ['mentor' => $mentor] = mentoringSetup($this->tenant);

    // Wed is fully off; Tue 10:30–11:00 IST is blocked by a window exception.
    MentorAvailabilityException::query()->create([
        'tenant_id' => $this->tenant->id, 'mentor_profile_id' => $mentor->id, 'date' => '2026-07-22',
    ]);
    MentorAvailabilityException::query()->create([
        'tenant_id' => $this->tenant->id, 'mentor_profile_id' => $mentor->id,
        'date' => '2026-07-21', 'start_minute' => 630, 'end_minute' => 660,
    ]);
    // 11:00 IST Tue is already booked.
    MentorSession::factory()->for($this->tenant)->create([
        'mentor_profile_id' => $mentor->id, 'starts_at' => '2026-07-21 05:30:00',
    ]);

    $slots = withinTenant($this->tenant, fn () => app(SlotFinder::class)->slotsFor($mentor, days: 3));
    $iso = $slots->map(fn ($s) => $s->toIso8601String())->all();

    // Tue window 10:00–12:00 = 4 half-hour slots; 10:30 blocked, 11:00 booked.
    expect($iso)->toBe(['2026-07-21T04:30:00+00:00', '2026-07-21T06:00:00+00:00']);
});

it('filters the combined calendar by expertise tag', function () {
    mentoringSetup($this->tenant);
    $qa = MentorProfile::factory()->for($this->tenant)->create(['expertise_tags' => ['testing']]);
    MentorAvailability::query()->create([
        'tenant_id' => $this->tenant->id, 'mentor_profile_id' => $qa->id,
        'weekday' => 2, 'start_minute' => 840, 'end_minute' => 900,
    ]);

    $all = withinTenant($this->tenant, fn () => app(SlotFinder::class)->combined());
    $testingOnly = withinTenant($this->tenant, fn () => app(SlotFinder::class)->combined('Testing'));

    expect(collect($all)->pluck('mentor_profile_id')->unique()->count())->toBe(2)
        ->and(collect($testingOnly)->pluck('mentor_profile_id')->unique()->all())->toBe([$qa->id]);
});

/* ---- Booking ---- */

it('books a slot: consumes a credit, creates Zoom, notifies both sides, and self-guards early reminders', function () {
    ['mentor' => $mentor, 'student' => $student] = mentoringSetup($this->tenant);
    Sanctum::actingAs($student);

    postJson('/api/v1/me/mentor-sessions', [
        'mentor_profile_id' => $mentor->id, 'starts_at' => tueTenIst(),
    ])->assertCreated()->assertJsonPath('data.status', 'booked');

    $session = MentorSession::withoutGlobalScopes()->sole();
    expect($session->zoom_meeting_id)->not->toBeNull()
        ->and($session->join_url)->toContain('zoom.test')
        ->and(app(EntitlementService::class)->balance($student, 'mentor'))->toBe(0)
        ->and($this->zoom->created[0]['duration'])->toBe(30);

    // Both sides told instantly; reminders armed but silent this far out.
    expect(Message::withoutGlobalScopes()->where('template_key', 'mentor_booked')->count())->toBe(2)
        ->and(Message::withoutGlobalScopes()->where('template_key', 'mentor_reminder')->count())->toBe(0)
        ->and($session->reminded_24h_at)->toBeNull();
});

it('answers 402 with the mentor top-up when the wallet is empty', function () {
    Product::query()->create([
        'tenant_id' => $this->tenant->id, 'sku' => 'mentor-extra', 'name' => 'Extra mentor 1:1',
        'feature' => 'mentor', 'kind' => 'pack', 'price_paise' => 49_900, 'grant_amount' => 1, 'active' => true,
    ]);
    ['mentor' => $mentor, 'student' => $student] = mentoringSetup($this->tenant, credits: 0);
    Sanctum::actingAs($student);

    postJson('/api/v1/me/mentor-sessions', [
        'mentor_profile_id' => $mentor->id, 'starts_at' => tueTenIst(),
    ])->assertStatus(402)
        ->assertJsonPath('error.code', 'no_mentor_credits')
        ->assertJsonPath('error.topups.0.sku', 'mentor-extra');

    expect(MentorSession::withoutGlobalScopes()->count())->toBe(0);
});

it('settles a double-book race: the second student keeps their credit', function () {
    ['mentor' => $mentor, 'student' => $first] = mentoringSetup($this->tenant);
    $second = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    app(EntitlementService::class)->grantCredits($second, 'mentor', 1, 'test');

    Sanctum::actingAs($first);
    postJson('/api/v1/me/mentor-sessions', ['mentor_profile_id' => $mentor->id, 'starts_at' => tueTenIst()])
        ->assertCreated();

    Sanctum::actingAs($second);
    postJson('/api/v1/me/mentor-sessions', ['mentor_profile_id' => $mentor->id, 'starts_at' => tueTenIst()])
        ->assertUnprocessable();

    expect(MentorSession::withoutGlobalScopes()->count())->toBe(1)
        ->and(app(EntitlementService::class)->balance($second, 'mentor'))->toBe(1);
});

it('rejects a time that is not a real slot', function () {
    ['mentor' => $mentor, 'student' => $student] = mentoringSetup($this->tenant);
    Sanctum::actingAs($student);

    postJson('/api/v1/me/mentor-sessions', [
        'mentor_profile_id' => $mentor->id, 'starts_at' => '2026-07-21T04:45:00+00:00', // off-grid
    ])->assertUnprocessable();
});

/* ---- Cancel / reschedule / reminders ---- */

it('cancels with notice: refunds the credit, deletes Zoom, and tells both sides', function () {
    ['mentor' => $mentor, 'student' => $student] = mentoringSetup($this->tenant);
    Sanctum::actingAs($student);
    $id = postJson('/api/v1/me/mentor-sessions', ['mentor_profile_id' => $mentor->id, 'starts_at' => tueTenIst()])
        ->json('data.id');

    postJson("/api/v1/me/mentor-sessions/{$id}/cancel")->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect(app(EntitlementService::class)->balance($student, 'mentor'))->toBe(1)
        ->and($this->zoom->deleted)->toHaveCount(1)
        ->and(Message::withoutGlobalScopes()->where('template_key', 'mentor_cancelled')->count())->toBe(2);
});

it('refuses a student cancel inside the 4-hour window', function () {
    ['mentor' => $mentor, 'student' => $student] = mentoringSetup($this->tenant);
    Sanctum::actingAs($student);
    $id = postJson('/api/v1/me/mentor-sessions', ['mentor_profile_id' => $mentor->id, 'starts_at' => tueTenIst()])
        ->json('data.id');

    Carbon::setTestNow('2026-07-21 02:00:00'); // 2.5h before start
    postJson("/api/v1/me/mentor-sessions/{$id}/cancel")->assertUnprocessable();
    expect(MentorSession::withoutGlobalScopes()->sole()->status)->toBe('booked');
});

it('reschedules to a valid slot, updates Zoom, and re-arms reminders', function () {
    ['mentor' => $mentor, 'student' => $student] = mentoringSetup($this->tenant);
    Sanctum::actingAs($student);
    $id = postJson('/api/v1/me/mentor-sessions', ['mentor_profile_id' => $mentor->id, 'starts_at' => tueTenIst()])
        ->json('data.id');

    $newStart = '2026-07-21T06:00:00+00:00'; // Tue 11:30 IST
    postJson("/api/v1/me/mentor-sessions/{$id}/reschedule", ['starts_at' => $newStart])
        ->assertOk()
        ->assertJsonPath('data.starts_at', $newStart);

    expect($this->zoom->updated)->toHaveCount(1)
        ->and(Message::withoutGlobalScopes()->where('template_key', 'mentor_rescheduled')->count())->toBe(2);

    // A reminder armed for the OLD start time dies on its guard.
    (new SendMentorReminder($id, '24h', Carbon::parse(tueTenIst())->timestamp))->handle(app(Messenger::class));
    expect(Message::withoutGlobalScopes()->where('template_key', 'mentor_reminder')->count())->toBe(0);
});

it('sends each reminder phase exactly once, inside its window', function () {
    ['mentor' => $mentor, 'student' => $student] = mentoringSetup($this->tenant);
    Sanctum::actingAs($student);
    $id = postJson('/api/v1/me/mentor-sessions', ['mentor_profile_id' => $mentor->id, 'starts_at' => tueTenIst()])
        ->json('data.id');
    $ts = MentorSession::withoutGlobalScopes()->sole()->starts_at->timestamp;

    Carbon::setTestNow('2026-07-20 05:00:00'); // ~23.5h before start
    (new SendMentorReminder($id, '24h', $ts))->handle(app(Messenger::class));
    (new SendMentorReminder($id, '24h', $ts))->handle(app(Messenger::class));

    expect(Message::withoutGlobalScopes()->where('template_key', 'mentor_reminder')->count())->toBe(2) // student + mentor, once
        ->and(MentorSession::withoutGlobalScopes()->sole()->reminded_24h_at)->not->toBeNull();
});

/* ---- No-show / feedback / rating ---- */

it('keeps the credit on a student no-show and refunds it on a mentor no-show', function () {
    ['mentor' => $mentor, 'student' => $student] = mentoringSetup($this->tenant, credits: 2);

    $sessions = collect(['2026-07-21 04:30:00', '2026-07-21 05:00:00'])->map(
        fn ($at) => MentorSession::factory()->for($this->tenant)->create([
            'mentor_profile_id' => $mentor->id, 'student_id' => $student->id, 'starts_at' => $at,
        ]),
    );

    Carbon::setTestNow('2026-07-21 06:00:00'); // both sessions past
    withinTenant($this->tenant, function () use ($sessions) {
        app(MarkMentorNoShow::class)->handle($sessions[0], 'student');
        app(MarkMentorNoShow::class)->handle($sessions[1], 'mentor');
    });

    expect($sessions[0]->fresh()->no_show_side)->toBe('student')
        ->and(app(EntitlementService::class)->balance($student, 'mentor'))->toBe(3); // 2 + 1 mentor refund
});

it('turns mentor feedback into a completed session, telemetry, and a PRI blend', function () {
    config(['scoring.pri.mentor_blend' => 0.2]);
    ['mentor' => $mentor, 'student' => $student] = mentoringSetup($this->tenant);
    $session = MentorSession::factory()->for($this->tenant)->create([
        'mentor_profile_id' => $mentor->id, 'student_id' => $student->id, 'starts_at' => '2026-07-19 10:00:00',
    ]);

    withinTenant($this->tenant, fn () => app(SubmitMentorFeedback::class)->handle($session, [
        'score' => 90, 'strengths' => 'Clear SQL fundamentals.', 'improvements' => 'Practise system design out loud.',
    ]));

    // Feedback also writes telemetry (engagement moves), so baseline at
    // blend 0 AFTER the feedback to isolate what the blend contributes.
    config(['scoring.pri.mentor_blend' => 0.0]);
    $base = app(ScoreCalculator::class)->computeFor($student)['pri'];
    config(['scoring.pri.mentor_blend' => 0.2]);
    $blended = app(ScoreCalculator::class)->computeFor($student)['pri'];

    expect($session->fresh()->status)->toBe('completed')
        ->and(ActivityEvent::withoutGlobalScopes()->where('type', 'mentor_feedback')->where('value', 90)->count())->toBe(1)
        ->and($blended)->toBe((int) round(0.8 * $base + 0.2 * 90));
});

it('records a student rating and surfaces the mentor average', function () {
    ['mentor' => $mentor, 'student' => $student] = mentoringSetup($this->tenant);
    $session = MentorSession::factory()->for($this->tenant)->create([
        'mentor_profile_id' => $mentor->id, 'student_id' => $student->id,
        'starts_at' => '2026-07-19 10:00:00', 'status' => 'completed',
    ]);
    Sanctum::actingAs($student);

    postJson("/api/v1/me/mentor-sessions/{$session->id}/rate", ['rating' => 5, 'comment' => 'Brilliant session.'])
        ->assertOk()->assertJsonPath('data.student_rating', 5);

    getJson('/api/v1/me/mentors')->assertOk()->assertJsonPath('data.mentors.0.rating', 5);
});

it('serves a valid ics invite', function () {
    ['mentor' => $mentor, 'student' => $student] = mentoringSetup($this->tenant);
    $session = MentorSession::factory()->for($this->tenant)->create([
        'mentor_profile_id' => $mentor->id, 'student_id' => $student->id,
        'starts_at' => '2026-07-21 04:30:00', 'join_url' => 'https://zoom.test/j/1',
    ]);
    Sanctum::actingAs($student);

    $response = getJson("/api/v1/me/mentor-sessions/{$session->id}/ics")->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/calendar')
        ->and($response->getContent())->toContain('BEGIN:VEVENT')
        ->toContain('DTSTART:20260721T043000Z')
        ->toContain("UID:mentor-session-{$session->id}@browsejobs.ai");
});

/* ---- Mentor hub + admin ---- */

it('gates the mentor hub on owning a profile and round-trips the availability editor', function () {
    ['mentor' => $mentor] = mentoringSetup($this->tenant);
    $outsider = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);

    Sanctum::actingAs($outsider);
    getJson('/api/v1/me/mentorhub')->assertForbidden();

    Sanctum::actingAs($mentor->user);
    getJson('/api/v1/me/mentorhub')->assertOk()->assertJsonPath('data.profile.id', $mentor->id);

    putJson('/api/v1/me/mentorhub/availability', [
        'windows' => [['weekday' => 5, 'start_minute' => 540, 'end_minute' => 660]],
        'exceptions' => [['date' => '2026-07-24']],
    ])->assertOk()
        ->assertJsonPath('data.windows.0.weekday', 5)
        ->assertJsonCount(1, 'data.windows');

    expect(MentorAvailability::withoutGlobalScopes()->where('mentor_profile_id', $mentor->id)->count())->toBe(1);
});

it('lets the mentor submit feedback only for their own sessions', function () {
    ['mentor' => $mentor, 'student' => $student] = mentoringSetup($this->tenant);
    $foreign = MentorProfile::factory()->for($this->tenant)->create();
    $foreignSession = MentorSession::factory()->for($this->tenant)->create([
        'mentor_profile_id' => $foreign->id, 'student_id' => $student->id, 'starts_at' => '2026-07-19 10:00:00',
    ]);

    Sanctum::actingAs($mentor->user);
    postJson("/api/v1/me/mentorhub/{$foreignSession->id}/feedback", [
        'score' => 80, 'strengths' => 'x', 'improvements' => 'y',
    ])->assertNotFound();
});

it('locks mentor administration behind manage-placements', function () {
    $this->seed(RolePermissionSeeder::class);
    $staff = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    Sanctum::actingAs($student);
    getJson('/api/v1/admin/mentors')->assertForbidden();

    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    postJson('/api/v1/admin/mentors', [
        'user_id' => $staff->id, 'headline' => 'Staff mentor', 'expertise' => ['java'],
    ])->assertCreated();

    getJson('/api/v1/admin/mentors')->assertOk()
        ->assertJsonPath('data.mentors.0.expertise.0', 'java');
});

/* ---- Coach recommendation ---- */

it('recommends a mentor 1:1 on persistent weakness (2+ weak modules after a completed mock)', function () {
    $course = Course::factory()->for($this->tenant)->create();
    $batch = Batch::factory()->for($this->tenant)->create(['course_id' => $course->id, 'type' => 'paid']);
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    BatchMember::factory()->for($this->tenant)->create([
        'batch_id' => $batch->id, 'user_id' => $student->id, 'status' => 'enrolled',
    ]);
    withinTenant($this->tenant, function () use ($course) {
        foreach ([1, 2] as $i) {
            $module = Module::factory()->create(['tenant_id' => $this->tenant->id, 'course_id' => $course->id, 'position' => $i]);
            Topic::factory()->create(['tenant_id' => $this->tenant->id, 'module_id' => $module->id]);
        }
    });

    // No mock yet → the P3.2 ladder is untouched (re-engage/next-topic class of action).
    $before = app(ScoreCalculator::class)->computeFor($student)['next_action']['key'];
    expect($before)->not->toBe('book_mentor');

    $blueprint = MockBlueprint::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    withinTenant($this->tenant, fn () => MockInterview::query()->create([
        'tenant_id' => $this->tenant->id, 'user_id' => $student->id, 'mock_blueprint_id' => $blueprint->id,
        'mode' => 'text', 'status' => 'completed', 'overall_score' => 45,
        'scorecard' => ['overall' => 45, 'competencies' => [], 'actions' => ['a', 'b', 'c']],
        'scorecard_source' => 'ai', 'started_at' => now()->subDay(), 'completed_at' => now()->subDay(),
    ]));

    $after = app(ScoreCalculator::class)->computeFor($student)['next_action'];
    expect($after['key'])->toBe('book_mentor')
        ->and($after['href'])->toBe('/mentors');
});

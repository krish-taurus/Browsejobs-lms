<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Celebration;
use App\Models\Course;
use App\Models\CvDocument;
use App\Models\InAppNotification;
use App\Models\InterviewTranscript;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\MentorProfile;
use App\Models\MentorSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->seed(RolePermissionSeeder::class);
});

/**
 * An enrolled student; pass pool: true to make them placement-eligible
 * (PRI floor lowered to 0, approved CV, passed human mock).
 *
 * @return array{student: User, course: Course}
 */
function placementStudent(Tenant $tenant, bool $pool = true): array
{
    $course = Course::factory()->for($tenant)->create(['name' => 'Data Engineering']);
    $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id, 'type' => 'paid']);
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    BatchMember::factory()->for($tenant)->create([
        'batch_id' => $batch->id, 'user_id' => $student->id, 'status' => 'enrolled',
    ]);

    if ($pool) {
        config(['placement.pool.min_pri' => 0]);
        CvDocument::factory()->for($tenant)->create([
            'user_id' => $student->id, 'status' => CvDocument::STATUS_APPROVED, 'approved_at' => now(),
        ]);
        $mentorUser = User::factory()->for($tenant)->create(['user_type' => 'staff']);
        $mentor = MentorProfile::factory()->for($tenant)->create(['user_id' => $mentorUser->id]);
        MentorSession::factory()->for($tenant)->create([
            'mentor_profile_id' => $mentor->id, 'student_id' => $student->id,
            'purpose' => MentorSession::PURPOSE_PLACEMENT, 'status' => MentorSession::STATUS_COMPLETED,
            'feedback_score' => 82, 'starts_at' => now()->subDay(),
        ]);
    }

    return ['student' => $student, 'course' => $course];
}

function placementOfficer(Tenant $tenant): User
{
    $user = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $user->assignRole('placement-officer');

    return $user;
}

/* ---- Pool gate ---- */

it('shows the three-part pool checklist and flips eligible when all pass', function () {
    ['student' => $student, 'course' => $course] = placementStudent($this->tenant, pool: false);
    JobPosting::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    Sanctum::actingAs($student);

    $pool = getJson('/api/v1/me/placement')->assertOk()->json('data.pool');
    expect($pool['eligible'])->toBeFalse()
        ->and($pool['checks']['pri']['ok'])->toBeFalse()
        ->and($pool['checks']['cv']['ok'])->toBeFalse()
        ->and($pool['checks']['human_mock']['ok'])->toBeFalse();

    // Meet all three bars.
    config(['placement.pool.min_pri' => 0]);
    CvDocument::factory()->for($this->tenant)->create(['user_id' => $student->id, 'status' => 'approved']);
    $mentorUser = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor = MentorProfile::factory()->for($this->tenant)->create(['user_id' => $mentorUser->id]);
    MentorSession::factory()->for($this->tenant)->create([
        'mentor_profile_id' => $mentor->id, 'student_id' => $student->id,
        'purpose' => 'placement_interview', 'status' => 'completed',
        'feedback_score' => 90, 'starts_at' => now()->subDay(),
    ]);

    expect(getJson('/api/v1/me/placement')->json('data.pool.eligible'))->toBeTrue();
});

it('refuses applications from students outside the pool', function () {
    ['student' => $student, 'course' => $course] = placementStudent($this->tenant, pool: false);
    $job = JobPosting::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    Sanctum::actingAs($student);

    postJson('/api/v1/me/applications', ['job_posting_id' => $job->id])->assertUnprocessable();
    expect(JobApplication::withoutGlobalScopes()->count())->toBe(0);
});

/* ---- Applications ---- */

it('applies with the latest approved CV, records consent, and blocks duplicates', function () {
    ['student' => $student, 'course' => $course] = placementStudent($this->tenant);
    $job = JobPosting::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    Sanctum::actingAs($student);

    postJson('/api/v1/me/applications', [
        'job_posting_id' => $job->id, 'celebrate_consent' => true, 'celebrate_anonymous' => true,
    ])->assertCreated()->assertJsonPath('data.status', 'applied');

    $application = JobApplication::withoutGlobalScopes()->sole();
    expect($application->cv_document_id)->not->toBeNull()
        ->and($application->celebrate_consent_at)->not->toBeNull()
        ->and($application->celebrate_anonymous)->toBeTrue();

    postJson('/api/v1/me/applications', ['job_posting_id' => $job->id])->assertUnprocessable();
});

it('scopes the job board to the student\'s courses plus open-to-all postings', function () {
    ['student' => $student, 'course' => $course] = placementStudent($this->tenant);
    $other = Course::factory()->for($this->tenant)->create(['name' => 'DevOps']);
    $mine = JobPosting::factory()->for($this->tenant)->create(['course_id' => $course->id, 'title' => 'DE role']);
    $all = JobPosting::factory()->for($this->tenant)->create(['course_id' => null, 'title' => 'Any-course role']);
    JobPosting::factory()->for($this->tenant)->create(['course_id' => $other->id, 'title' => 'DevOps role']);
    JobPosting::factory()->for($this->tenant)->create(['course_id' => $course->id, 'status' => 'closed']);
    Sanctum::actingAs($student);

    $jobs = collect(getJson('/api/v1/me/placement')->json('data.jobs'));
    expect($jobs->pluck('id')->sort()->values()->all())->toBe([$mine->id, $all->id]);
});

/* ---- Debrief rounds → interview bank ---- */

it('pipes a shared round debrief into the interview-bank pipeline', function () {
    ['student' => $student, 'course' => $course] = placementStudent($this->tenant);
    $job = JobPosting::factory()->for($this->tenant)->create(['course_id' => $course->id, 'title' => 'Data Engineer']);
    Sanctum::actingAs($student);
    $id = postJson('/api/v1/me/applications', ['job_posting_id' => $job->id])->json('data.id');

    $this->fake->reply = json_encode(['outcome' => 'next_round', 'questions' => [[
        'question' => 'How do you partition a large fact table?', 'normalized' => 'partition large fact table',
        'topics' => ['sql optimisation'], 'difficulty' => 'medium', 'round' => 'technical',
        'follow_ups' => [], 'strong_answer' => null, 'struggle' => null, 'confidence' => 'high',
    ]]], JSON_THROW_ON_ERROR);

    postJson("/api/v1/me/applications/{$id}/rounds", [
        'happened_on' => now()->toDateString(), 'outcome' => 'advanced', 'round_type' => 'technical',
        'debrief' => 'They asked about partitioning a large fact table and index strategy.',
        'share_debrief' => true,
    ])->assertCreated()->assertJsonPath('data.rounds.0.shared', true);

    $transcript = InterviewTranscript::withoutGlobalScopes()->sole();
    expect($transcript->source)->toBe('debrief')
        ->and($transcript->role_title)->toBe('Data Engineer')
        ->and($transcript->status)->toBe(InterviewTranscript::STATUS_PARSED)
        ->and($transcript->questions()->withoutGlobalScopes()->count())->toBe(1);
});

it('keeps an unshared debrief private', function () {
    ['student' => $student, 'course' => $course] = placementStudent($this->tenant);
    $job = JobPosting::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    Sanctum::actingAs($student);
    $id = postJson('/api/v1/me/applications', ['job_posting_id' => $job->id])->json('data.id');

    postJson("/api/v1/me/applications/{$id}/rounds", [
        'happened_on' => now()->toDateString(), 'outcome' => 'pending',
        'debrief' => 'Private notes about my performance.', 'share_debrief' => false,
    ])->assertCreated()->assertJsonPath('data.rounds.0.shared', false);

    expect(InterviewTranscript::withoutGlobalScopes()->count())->toBe(0);
});

it('lets a student withdraw an active application but never a placement', function () {
    ['student' => $student, 'course' => $course] = placementStudent($this->tenant);
    $job = JobPosting::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    Sanctum::actingAs($student);
    $id = postJson('/api/v1/me/applications', ['job_posting_id' => $job->id])->json('data.id');

    postJson("/api/v1/me/applications/{$id}/withdraw")->assertOk()
        ->assertJsonPath('data.status', 'withdrawn');

    JobApplication::withoutGlobalScopes()->whereKey($id)->update(['status' => 'placed']);
    postJson("/api/v1/me/applications/{$id}/withdraw")->assertUnprocessable();
});

/* ---- Officer pipeline ---- */

it('locks the officer board and job management behind manage-placements', function () {
    ['student' => $student] = placementStudent($this->tenant, pool: false);
    Sanctum::actingAs($student);
    getJson('/api/v1/admin/placements')->assertForbidden();

    Sanctum::actingAs(placementOfficer($this->tenant));
    postJson('/api/v1/admin/jobs', [
        'title' => 'Junior Data Engineer', 'company' => 'Meridian Analytics',
        'description' => 'Entry-level pipeline work with SQL and Python.',
    ])->assertCreated();

    getJson('/api/v1/admin/placements')->assertOk()
        ->assertJsonPath('data.jobs.0.company', 'Meridian Analytics');
});

it('marks a consented placement: celebration draft, notifications, audit, and fee milestone flag', function () {
    ['student' => $student, 'course' => $course] = placementStudent($this->tenant);
    $job = JobPosting::factory()->for($this->tenant)->create(['course_id' => $course->id, 'company' => 'Meridian Analytics']);
    Sanctum::actingAs($student);
    $id = postJson('/api/v1/me/applications', [
        'job_posting_id' => $job->id, 'celebrate_consent' => true, 'celebrate_anonymous' => true,
    ])->json('data.id');

    $officer = placementOfficer($this->tenant);
    Sanctum::actingAs($officer);

    patchJson("/api/v1/admin/applications/{$id}", [
        'status' => 'placed', 'offer_ctc_paise' => 60_000_000, 'offer_role' => 'Data Engineer I',
    ])->assertOk()->assertJsonPath('data.status', 'placed');

    $application = JobApplication::withoutGlobalScopes()->sole();
    expect($application->placed_at)->not->toBeNull();

    $celebration = Celebration::withoutGlobalScopes()->sole();
    expect($celebration->display_mode)->toBe('anonymous')
        ->and($celebration->company)->toBe('Meridian Analytics')
        ->and($celebration->published_at)->toBeNull(); // draft — staff publish it

    expect(AuditLog::withoutGlobalScopes()->where('action', 'placement.placed')->count())->toBe(1)
        ->and(InAppNotification::withoutGlobalScopes()->where('user_id', $student->id)->where('url', '/placement')->count())->toBe(1)
        ->and(InAppNotification::withoutGlobalScopes()->where('user_id', $officer->id)->count())->toBe(1);

    // Re-marking placed never duplicates side effects.
    patchJson("/api/v1/admin/applications/{$id}", ['status' => 'placed'])->assertOk();
    expect(Celebration::withoutGlobalScopes()->count())->toBe(1);
});

it('skips the celebration entirely without consent', function () {
    ['student' => $student, 'course' => $course] = placementStudent($this->tenant);
    $job = JobPosting::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    Sanctum::actingAs($student);
    $id = postJson('/api/v1/me/applications', ['job_posting_id' => $job->id])->json('data.id');

    Sanctum::actingAs(placementOfficer($this->tenant));
    patchJson("/api/v1/admin/applications/{$id}", ['status' => 'placed'])->assertOk();

    expect(Celebration::withoutGlobalScopes()->count())->toBe(0);
});

/* ---- Proof Engine ---- */

it('serves anonymised placement aggregates with the mandatory disclaimer', function () {
    $tenant = Tenant::factory()->domain('acme.test')->create();
    $posting = JobPosting::factory()->for($tenant)->create(['company' => 'Meridian Analytics']);
    JobApplication::factory()->for($tenant)->count(2)->create([
        'job_posting_id' => $posting->id, 'status' => 'placed', 'placed_at' => now()->subDays(10),
    ]);
    JobApplication::factory()->for($tenant)->create(['job_posting_id' => $posting->id, 'status' => 'offer']);

    $data = $this->getJson('http://acme.test/api/v1/proof')->assertOk()->json('data');

    expect($data['placed_count'])->toBe(2)
        ->and($data['offers_count'])->toBe(3)
        ->and($data['hiring_companies'])->toBe(1)
        ->and($data['disclaimer'])->toContain('not a promise');
});

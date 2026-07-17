<?php

declare(strict_types=1);

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Events\CourseCompleted;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\CvDocument;
use App\Models\InAppNotification;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\TopicCompletion;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Entitlements\EntitlementService;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
});

/** A valid AI CV reply. */
function cvReply(string $headline = 'Data Engineer — SQL & Python'): string
{
    return json_encode([
        'headline' => $headline,
        'summary' => 'Completed the Data Engineering track with graded lab work across 4 modules.',
        'skills' => ['sql', 'python', 'airflow'],
        'projects' => [['name' => 'ETL pipeline lab', 'bullets' => ['Built a batch ETL pipeline passing 6 automated tests']]],
        'education' => [['name' => 'Data Engineering', 'detail' => 'BrowseJobs bootcamp']],
        'certifications' => [],
    ], JSON_THROW_ON_ERROR);
}

/**
 * An enrolled student on a course with one fully-completed module.
 *
 * @return array{student: User, course: Course}
 */
function cvReadyStudent(Tenant $tenant): array
{
    $course = Course::factory()->for($tenant)->create(['name' => 'Data Engineering']);
    $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id, 'type' => BatchType::Paid->value]);
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    BatchMember::factory()->for($tenant)->create([
        'batch_id' => $batch->id, 'user_id' => $student->id, 'status' => BatchMemberStatus::Enrolled->value,
    ]);

    withinTenant($tenant, function () use ($tenant, $course, $student) {
        $module = Module::factory()->create(['tenant_id' => $tenant->id, 'course_id' => $course->id, 'name' => 'SQL Foundations']);
        $topic = Topic::factory()->create(['tenant_id' => $tenant->id, 'module_id' => $module->id]);
        TopicCompletion::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => $student->id, 'topic_id' => $topic->id, 'completed_at' => now(),
        ]);
    });

    return ['student' => $student, 'course' => $course];
}

function cvCredits(User $student): int
{
    return app(EntitlementService::class)->balance($student, 'cv');
}

/* ---- Auto-generation on course completion ---- */

it('auto-builds the first CV free on course completion, grants included credits once, and celebrates', function () {
    ['student' => $student, 'course' => $course] = cvReadyStudent($this->tenant);
    $this->fake->reply = cvReply();

    CourseCompleted::dispatch($student, $course);

    $cv = CvDocument::withoutGlobalScopes()->sole();
    expect($cv->source)->toBe('auto')
        ->and($cv->version)->toBe(1)
        ->and($cv->status)->toBe('draft')
        ->and($cv->content['headline'])->toBe('Data Engineer — SQL & Python')
        ->and($cv->content['email'])->toBe($student->email)
        ->and($cv->ats['parse_score'])->toBeGreaterThan(0)
        ->and(cvCredits($student))->toBe(3) // monetization default free grants
        ->and(InAppNotification::withoutGlobalScopes()->where('url', '/cv')->count())->toBe(1);

    // Re-firing the event never duplicates the CV or the grant.
    CourseCompleted::dispatch($student, $course);
    expect(CvDocument::withoutGlobalScopes()->count())->toBe(1)
        ->and(cvCredits($student))->toBe(3);
});

/* ---- Generation + credits ---- */

it('consumes one credit per manual generation and increments the version', function () {
    ['student' => $student] = cvReadyStudent($this->tenant);
    app(EntitlementService::class)->grantCredits($student, 'cv', 2, 'test');
    $this->fake->replies = [cvReply(), cvReply('Data Engineer — v2')];
    Sanctum::actingAs($student);

    postJson('/api/v1/me/cv')->assertCreated()->assertJsonPath('data.version', 1);
    postJson('/api/v1/me/cv')->assertCreated()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.content.headline', 'Data Engineer — v2');

    expect(cvCredits($student))->toBe(0);
});

it('answers 402 with the 99-rupee pack when generations run out', function () {
    Product::query()->create([
        'tenant_id' => $this->tenant->id, 'sku' => 'cv-3pack', 'name' => 'CV generations · 3-pack',
        'feature' => 'cv', 'kind' => 'pack', 'price_paise' => 9_900, 'grant_amount' => 3, 'active' => true,
    ]);
    ['student' => $student] = cvReadyStudent($this->tenant);
    Sanctum::actingAs($student);

    postJson('/api/v1/me/cv')->assertStatus(402)
        ->assertJsonPath('error.code', 'no_cv_credits')
        ->assertJsonPath('error.topups.0.sku', 'cv-3pack')
        ->assertJsonPath('error.topups.0.price_paise', 9_900);

    expect(CvDocument::withoutGlobalScopes()->count())->toBe(0);
});

it('tailors to a pasted JD: the prompt carries the JD and the report scores the match', function () {
    ['student' => $student] = cvReadyStudent($this->tenant);
    app(EntitlementService::class)->grantCredits($student, 'cv', 1, 'test');
    $this->fake->reply = cvReply();
    Sanctum::actingAs($student);

    $jd = 'We need SQL, Python, Airflow and Kubernetes experience for our data platform team.';
    $response = postJson('/api/v1/me/cv', ['jd' => $jd])->assertCreated()
        ->assertJsonPath('data.source', 'jd_tailored');

    expect($this->fake->calls[0]->user)->toContain('Kubernetes')
        ->and($response->json('data.ats.jd_match.matched'))->toContain('sql')
        ->and($response->json('data.ats.jd_match.missing'))->toContain('kubernetes');
});

it('falls back to a deterministic CV built from real facts when the AI returns garbage', function () {
    ['student' => $student] = cvReadyStudent($this->tenant);
    app(EntitlementService::class)->grantCredits($student, 'cv', 1, 'test');
    $this->fake->reply = 'not json, sorry';
    Sanctum::actingAs($student);

    postJson('/api/v1/me/cv')->assertCreated()
        ->assertJsonPath('data.content_source', 'fallback')
        ->assertJsonPath('data.content.skills.0', 'SQL Foundations'); // the completed module
});

/* ---- Edits + ATS ---- */

it('lets students edit text free, resetting approval', function () {
    ['student' => $student] = cvReadyStudent($this->tenant);
    $cv = CvDocument::factory()->for($this->tenant)->create([
        'user_id' => $student->id, 'status' => CvDocument::STATUS_APPROVED, 'approved_at' => now(),
    ]);
    Sanctum::actingAs($student);

    patchJson("/api/v1/me/cv/{$cv->id}", [
        'content' => ['summary' => 'Sharper summary with 3 quantified achievements.'],
    ])->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.content.summary', 'Sharper summary with 3 quantified achievements.')
        ->assertJsonPath('data.content.headline', 'Data Engineer in training'); // untouched fields survive

    expect(cvCredits($student))->toBe(0); // edits never touch the wallet
});

it('scores the ATS panel deterministically: lint, quantified bullets, and verbs', function () {
    ['student' => $student] = cvReadyStudent($this->tenant);
    $cv = CvDocument::factory()->for($this->tenant)->create([
        'user_id' => $student->id,
        'content' => [
            'name' => 'S', 'email' => null, 'phone' => null,
            'headline' => '', 'summary' => 'Summary.',
            'skills' => ['sql'],
            'projects' => [['name' => 'Lab', 'bullets' => ['Built a pipeline passing 6 tests', 'worked on various things without measurable outcomes here']]],
        ],
    ]);
    Sanctum::actingAs($student);

    $report = postJson("/api/v1/me/cv/{$cv->id}/ats")->assertOk()->json('data');

    expect($report['quantified_pct'])->toBe(50)
        ->and($report['action_verb_pct'])->toBe(50)
        ->and(collect($report['lint'])->join(' '))->toContain('headline');
});

/* ---- Share links ---- */

it('mints a share link readable without auth and revokes it cleanly', function () {
    ['student' => $student] = cvReadyStudent($this->tenant);
    $cv = CvDocument::factory()->for($this->tenant)->create(['user_id' => $student->id]);
    Sanctum::actingAs($student);

    $url = postJson("/api/v1/me/cv/{$cv->id}/share")->assertOk()->json('data.share_url');
    $token = basename($url);

    auth()->guard('web')->logout();
    getJson("/api/v1/cv/shared/{$token}")->assertOk()
        ->assertJsonPath('data.content.headline', 'Data Engineer in training');

    Sanctum::actingAs($student);
    deleteJson("/api/v1/me/cv/{$cv->id}/share")->assertOk();
    getJson("/api/v1/cv/shared/{$token}")->assertNotFound();
});

/* ---- Approval + isolation ---- */

it('routes approval through the placement officer, audit-logged', function () {
    $this->seed(RolePermissionSeeder::class);
    ['student' => $student] = cvReadyStudent($this->tenant);
    $cv = CvDocument::factory()->for($this->tenant)->create(['user_id' => $student->id]);

    Sanctum::actingAs($student);
    getJson('/api/v1/admin/cvs')->assertForbidden();

    $officer = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $officer->assignRole('placement-officer');
    Sanctum::actingAs($officer);

    getJson('/api/v1/admin/cvs')->assertOk()->assertJsonPath('data.pending.0.id', $cv->id);
    postJson("/api/v1/admin/cvs/{$cv->id}/approve")->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($cv->fresh()->approved_by)->toBe($officer->id)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'cv.approved')->count())->toBe(1);
});

it('never exposes another student\'s CV', function () {
    ['student' => $student] = cvReadyStudent($this->tenant);
    $other = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $foreign = CvDocument::factory()->for($this->tenant)->create(['user_id' => $other->id]);

    Sanctum::actingAs($student);
    getJson("/api/v1/me/cv/{$foreign->id}")->assertNotFound();
    patchJson("/api/v1/me/cv/{$foreign->id}", ['content' => ['summary' => 'hijack']])->assertNotFound();
});

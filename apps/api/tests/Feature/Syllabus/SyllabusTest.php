<?php

declare(strict_types=1);

use App\Actions\Content\ApproveSyllabus;
use App\Actions\Content\GenerateSyllabus;
use App\Enums\SyllabusStatus;
use App\Events\CurriculumChanged;
use App\Jobs\RenderSyllabus;
use App\Listeners\RegenerateSyllabusOnCurriculumChange;
use App\Models\AiEvent;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Lead;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Syllabus;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Syllabus\SyllabusHasher;
use App\Support\Syllabus\SyllabusRenderer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    $this->fake->reply = "## Overview\n\nA practical, honest course.\n\n## Learning outcomes\n\n- Build data pipelines\n- Ship a capstone";
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
});

/** A course with a small curriculum tree in the tenant. */
function syllabusCourse(Tenant $tenant, string $slug = 'data-engineering'): Course
{
    $course = Course::factory()->for($tenant)->create([
        'name' => 'Data Engineering', 'slug' => $slug, 'description' => 'Reverse-engineered from interviews.',
    ]);
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id, 'name' => 'Foundations', 'position' => 0]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id, 'name' => 'SQL', 'position' => 0]);
    Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'title' => 'Joins', 'type' => 'video', 'position' => 0]);

    return $course;
}

function syllabusStaff(Tenant $tenant): User
{
    return User::factory()->for($tenant)->create(['user_type' => 'staff']);
}

/* ---- Generation ---- */

it('drafts syllabus content from the AI, stamps the curriculum hash, and bills the staff user', function () {
    $course = syllabusCourse($this->tenant);
    $staff = syllabusStaff($this->tenant);
    $syllabus = Syllabus::factory()->for($this->tenant)->create(['course_id' => $course->id, 'content' => null]);

    $ok = app(GenerateSyllabus::class)->handle($syllabus, $staff);

    expect($ok)->toBeTrue()
        ->and($syllabus->fresh()->content)->toContain('Overview')
        ->and($syllabus->fresh()->status)->toBe(SyllabusStatus::Draft)
        ->and($syllabus->fresh()->curriculum_hash)->not->toBeNull();

    expect(AiEvent::withoutGlobalScopes()->where('user_id', $staff->id)->where('purpose', 'syllabus')->exists())->toBeTrue();
});

it('leaves content untouched when the AI returns nothing (manual authoring fail-safe)', function () {
    $this->fake->reply = '';
    $course = syllabusCourse($this->tenant);
    $staff = syllabusStaff($this->tenant);
    $syllabus = Syllabus::factory()->for($this->tenant)->create(['course_id' => $course->id, 'content' => null]);

    $ok = app(GenerateSyllabus::class)->handle($syllabus, $staff);

    expect($ok)->toBeFalse()->and($syllabus->fresh()->content)->toBeNull();
});

it('leaves content untouched and does not throw when the AI budget is exceeded', function () {
    config(['ai.daily_token_budget' => 10]);
    $course = syllabusCourse($this->tenant);
    $staff = syllabusStaff($this->tenant);
    AiEvent::factory()->for($this->tenant)->create(['user_id' => $staff->id, 'total_tokens' => 100, 'created_at' => now()]);
    $syllabus = Syllabus::factory()->for($this->tenant)->create(['course_id' => $course->id, 'content' => null]);

    $ok = app(GenerateSyllabus::class)->handle($syllabus, $staff);

    expect($ok)->toBeFalse()->and($syllabus->fresh()->content)->toBeNull();
});

it('changes the curriculum hash when the tree changes', function () {
    $course = syllabusCourse($this->tenant);
    $hasher = app(SyllabusHasher::class);
    $before = $hasher->hash($course->fresh());

    Topic::factory()->for($this->tenant)->create(['module_id' => $course->modules()->first()->id, 'name' => 'Streaming', 'position' => 1]);

    expect($hasher->hash($course->fresh()))->not->toBe($before);
});

/* ---- Approval + render ---- */

it('approves a syllabus, audits it, renders the branded doc, and is idempotent', function () {
    $course = syllabusCourse($this->tenant);
    $staff = syllabusStaff($this->tenant);
    $syllabus = Syllabus::factory()->for($this->tenant)->create(['course_id' => $course->id]);

    app(ApproveSyllabus::class)->handle($syllabus, $staff);
    app(ApproveSyllabus::class)->handle($syllabus->fresh(), $staff); // no-op

    $fresh = $syllabus->fresh();
    expect($fresh->status)->toBe(SyllabusStatus::Approved)
        ->and($fresh->render_status)->toBe(Syllabus::RENDER_RENDERED)
        ->and($fresh->isDownloadable())->toBeTrue()
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'syllabus.approved')->count())->toBe(1);

    Storage::disk('s3')->assertExists($fresh->storage_path);
    expect(Storage::disk('s3')->get($fresh->storage_path))->toContain('Course Syllabus');
});

it('refuses to approve an empty syllabus', function () {
    $course = syllabusCourse($this->tenant);
    $syllabus = Syllabus::factory()->for($this->tenant)->create(['course_id' => $course->id, 'content' => null]);

    expect(fn () => app(ApproveSyllabus::class)->handle($syllabus, null))
        ->toThrow(ValidationException::class);
});

it('never renders an unapproved draft', function () {
    $course = syllabusCourse($this->tenant);
    $syllabus = Syllabus::factory()->for($this->tenant)->create([
        'course_id' => $course->id, 'status' => SyllabusStatus::Draft->value, 'render_status' => Syllabus::RENDER_PENDING,
    ]);

    (new RenderSyllabus($syllabus->id))->handle(app(SyllabusRenderer::class));

    expect($syllabus->fresh()->render_status)->toBe(Syllabus::RENDER_PENDING);
});

/* ---- Regenerate on curriculum change ---- */

it('regenerates a new draft on curriculum change while the last approved stays live', function () {
    $course = syllabusCourse($this->tenant);
    $staff = syllabusStaff($this->tenant);
    $syllabus = Syllabus::factory()->for($this->tenant)->create(['course_id' => $course->id]);

    app(GenerateSyllabus::class)->handle($syllabus, $staff);
    app(ApproveSyllabus::class)->handle($syllabus->fresh(), $staff);
    $approved = $syllabus->fresh();
    $oldPath = $approved->storage_path;
    $oldVersion = $approved->version;
    expect($approved->isDownloadable())->toBeTrue();

    // Real curriculum change, then the listener regenerates the draft.
    Topic::factory()->for($this->tenant)->create(['module_id' => $course->modules()->first()->id, 'name' => 'Streaming', 'position' => 1]);
    app(RegenerateSyllabusOnCurriculumChange::class)->handle(new CurriculumChanged($course->id, $this->tenant->id));

    $after = $syllabus->fresh();
    expect($after->status)->toBe(SyllabusStatus::Draft)      // fresh unapproved draft
        ->and($after->is_stale)->toBeTrue()
        ->and($after->version)->toBe($oldVersion + 1)
        ->and($after->storage_path)->toBe($oldPath)          // last approved artifact untouched
        ->and($after->isDownloadable())->toBeTrue();         // still serves the last approved
});

it('does not regenerate when the curriculum is unchanged', function () {
    $course = syllabusCourse($this->tenant);
    $staff = syllabusStaff($this->tenant);
    $syllabus = Syllabus::factory()->for($this->tenant)->create(['course_id' => $course->id]);

    app(GenerateSyllabus::class)->handle($syllabus, $staff);
    app(ApproveSyllabus::class)->handle($syllabus->fresh(), $staff);
    $version = $syllabus->fresh()->version;

    app(RegenerateSyllabusOnCurriculumChange::class)->handle(new CurriculumChanged($course->id, $this->tenant->id));

    expect($syllabus->fresh()->version)->toBe($version)
        ->and($syllabus->fresh()->status)->toBe(SyllabusStatus::Approved);
});

/* ---- Public lead-gated download ---- */

it('serves an approved syllabus publicly and captures a syllabus lead', function () {
    $tenant = Tenant::factory()->domain('acme.test')->create();
    $course = syllabusCourse($tenant, 'python-backend');
    $syllabus = Syllabus::factory()->for($tenant)->create(['course_id' => $course->id]);
    app(ApproveSyllabus::class)->handle($syllabus, syllabusStaff($tenant));

    postJson('http://acme.test/api/v1/courses/python-backend/syllabus/download', [
        'name' => 'Asha Rao', 'phone' => '+919000000001', 'consent' => true,
    ])->assertOk()->assertJsonStructure(['data' => ['download']]);

    expect(Lead::withoutGlobalScopes()->where('lead_type', 'syllabus')->count())->toBe(1);
});

it('404s the public download for a course with no approved syllabus and captures no lead', function () {
    $tenant = Tenant::factory()->domain('acme.test')->create();
    syllabusCourse($tenant, 'python-backend'); // no syllabus row

    postJson('http://acme.test/api/v1/courses/python-backend/syllabus/download', [
        'name' => 'Asha Rao', 'phone' => '+919000000001', 'consent' => true,
    ])->assertNotFound();

    expect(Lead::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects a public download without consent', function () {
    $tenant = Tenant::factory()->domain('acme.test')->create();
    $course = syllabusCourse($tenant, 'python-backend');
    $syllabus = Syllabus::factory()->for($tenant)->create(['course_id' => $course->id]);
    app(ApproveSyllabus::class)->handle($syllabus, syllabusStaff($tenant));

    postJson('http://acme.test/api/v1/courses/python-backend/syllabus/download', [
        'name' => 'Asha Rao', 'phone' => '+919000000001', 'consent' => false,
    ])->assertStatus(422);
});

/* ---- Student dashboard ---- */

it('gives an enrolled-tenant student a download only when the syllabus is approved', function () {
    $course = syllabusCourse($this->tenant);
    $syllabus = Syllabus::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    app(ApproveSyllabus::class)->handle($syllabus, syllabusStaff($this->tenant));

    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($student);

    getJson("/api/v1/me/courses/{$course->id}/syllabus")
        ->assertOk()
        ->assertJsonPath('data.downloadable', true);
});

it('hides a draft syllabus from the student dashboard', function () {
    $course = syllabusCourse($this->tenant);
    Syllabus::factory()->for($this->tenant)->create(['course_id' => $course->id]); // draft, unrendered

    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($student);

    getJson("/api/v1/me/courses/{$course->id}/syllabus")
        ->assertOk()
        ->assertJsonPath('data.downloadable', false)
        ->assertJsonPath('data.download', null);
});

it('requires auth for the student syllabus endpoint', function () {
    $course = syllabusCourse($this->tenant);
    getJson("/api/v1/me/courses/{$course->id}/syllabus")->assertUnauthorized();
});

it('does not serve another tenant\'s course syllabus to a student', function () {
    $other = Tenant::factory()->create();
    $foreign = syllabusCourse($other, 'foreign');

    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    Sanctum::actingAs($student);

    getJson("/api/v1/me/courses/{$foreign->id}/syllabus")->assertNotFound();
});

/* ---- Admin gate + cross-tenant ---- */

it('lets a curriculum manager generate, edit, and approve a syllabus over http', function () {
    $this->seed(RolePermissionSeeder::class);
    $course = syllabusCourse($this->tenant);
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    postJson("/api/v1/admin/courses/{$course->id}/syllabus/generate", [])->assertStatus(202);

    patchJson("/api/v1/admin/courses/{$course->id}/syllabus", ['content' => "## Overview\n\nHand-written."])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');

    $syllabus = Syllabus::withoutGlobalScopes()->where('course_id', $course->id)->firstOrFail();
    postJson("/api/v1/admin/syllabuses/{$syllabus->id}/approve", [])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.downloadable', true);
});

it('denies syllabus management without manage-curriculum', function () {
    $this->seed(RolePermissionSeeder::class);
    Sanctum::actingAs(counselorIn($this->tenant));

    getJson('/api/v1/admin/course-syllabuses')->assertForbidden();
});

it('does not approve another tenant\'s syllabus', function () {
    $this->seed(RolePermissionSeeder::class);
    $other = Tenant::factory()->create();
    $foreignCourse = syllabusCourse($other, 'foreign');
    $foreign = Syllabus::factory()->for($other)->create(['course_id' => $foreignCourse->id]);

    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    postJson("/api/v1/admin/syllabuses/{$foreign->id}/approve", [])->assertNotFound();
});

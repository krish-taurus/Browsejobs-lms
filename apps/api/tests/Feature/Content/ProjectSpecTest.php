<?php

declare(strict_types=1);

use App\Models\AiEvent;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\ProjectSpec;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Storage::fake('s3');
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

function aProjectLesson(Tenant $tenant): Lesson
{
    $course = Course::factory()->for($tenant)->create(['name' => 'Data Engineering']);
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);

    return Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'project', 'title' => 'Capstone']);
}

function aProjectAdmin(Tenant $tenant): User
{
    $admin = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');

    return $admin;
}

/** A saved spec with title + tech stack in the given tenant. */
function saveSpec(Lesson $lesson): array
{
    return putJson("/api/v1/admin/lessons/{$lesson->id}/project", [
        'title' => 'Real-time sales pipeline',
        'summary' => 'Stream orders into a warehouse and expose a dashboard.',
        'tech_stack' => ['Kafka', 'Spark', 'Snowflake', 'dbt'],
    ])->json('data');
}

const ARCH_JSON = '{"overview":"Orders flow through Kafka into Spark, land in Snowflake, and dbt models feed a dashboard.","layers":[{"name":"Ingestion","components":[{"name":"Kafka","role":"streaming ingest"}]},{"name":"Processing","components":[{"name":"Spark","role":"stream transform"}]},{"name":"Storage","components":[{"name":"Snowflake","role":"warehouse"}]}],"flows":[{"from":"Kafka","to":"Spark","label":"events"},{"from":"Spark","to":"Snowflake","label":"loads"}]}';

/* ---- Admin surface ---- */

it('saves the project brief and tech stack', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aProjectLesson($this->tenant);
    Sanctum::actingAs(aProjectAdmin($this->tenant));

    putJson("/api/v1/admin/lessons/{$lesson->id}/project", [
        'title' => 'Real-time sales pipeline',
        'summary' => 'Stream orders into a warehouse.',
        'tech_stack' => ['Kafka', 'Spark', 'Snowflake'],
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Real-time sales pipeline')
        ->assertJsonPath('data.tech_stack', ['Kafka', 'Spark', 'Snowflake'])
        ->assertJsonPath('data.architecture_source', 'none');

    expect(ProjectSpec::withoutGlobalScopes()->where('lesson_id', $lesson->id)->count())->toBe(1);
});

it('AI-drafts an architecture from the saved spec without persisting it', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aProjectLesson($this->tenant);
    $admin = aProjectAdmin($this->tenant);
    Sanctum::actingAs($admin);
    saveSpec($lesson);
    $this->fake->reply = 'Here is the design: '.ARCH_JSON;

    postJson("/api/v1/admin/lessons/{$lesson->id}/project/architecture")
        ->assertOk()
        ->assertJsonPath('data.overview', 'Orders flow through Kafka into Spark, land in Snowflake, and dbt models feed a dashboard.')
        ->assertJsonCount(3, 'data.layers')
        ->assertJsonPath('data.layers.0.components.0.name', 'Kafka');

    // Draft only — not persisted onto the spec until the trainer saves it.
    expect(ProjectSpec::withoutGlobalScopes()->where('lesson_id', $lesson->id)->first()->architecture)->toBeNull();
    // Billed to the staff actor.
    expect(AiEvent::withoutGlobalScopes()->where('user_id', $admin->id)->exists())->toBeTrue();
});

it('422s when generating before the spec is saved', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aProjectLesson($this->tenant);
    Sanctum::actingAs(aProjectAdmin($this->tenant));

    postJson("/api/v1/admin/lessons/{$lesson->id}/project/architecture")->assertStatus(422);
});

it('422s on unparseable architecture output', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aProjectLesson($this->tenant);
    Sanctum::actingAs(aProjectAdmin($this->tenant));
    saveSpec($lesson);
    $this->fake->reply = 'I cannot help with that.';

    postJson("/api/v1/admin/lessons/{$lesson->id}/project/architecture")
        ->assertStatus(422)->assertJsonPath('error.code', 'generation_failed');
});

it('renders the saved architecture into a downloadable branded PDF', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aProjectLesson($this->tenant);
    Sanctum::actingAs(aProjectAdmin($this->tenant));
    // Save the spec WITH an architecture structure.
    putJson("/api/v1/admin/lessons/{$lesson->id}/project", [
        'title' => 'Real-time sales pipeline',
        'summary' => 'Stream orders.',
        'tech_stack' => ['Kafka', 'Spark'],
        'architecture' => json_decode(ARCH_JSON, true),
    ])->assertOk();

    $res = postJson("/api/v1/admin/lessons/{$lesson->id}/project/pdf")->assertOk();
    expect($res->json('data.url'))->not->toBeNull();

    $spec = ProjectSpec::withoutGlobalScopes()->where('lesson_id', $lesson->id)->first();
    expect($spec->architecture_source->value)->toBe('ai')
        ->and($spec->architecture_path)->not->toBeNull();
    Storage::disk('s3')->assertExists($spec->architecture_path);
    expect(Storage::disk('s3')->get($spec->architecture_path))->toStartWith('%PDF');
});

it('refuses to render a PDF with no architecture', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aProjectLesson($this->tenant);
    Sanctum::actingAs(aProjectAdmin($this->tenant));
    saveSpec($lesson); // no architecture

    postJson("/api/v1/admin/lessons/{$lesson->id}/project/pdf")->assertStatus(422);
});

it('accepts an uploaded architecture file as the download', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aProjectLesson($this->tenant);
    Sanctum::actingAs(aProjectAdmin($this->tenant));
    saveSpec($lesson);

    postJson("/api/v1/admin/lessons/{$lesson->id}/project/upload", [
        'file' => UploadedFile::fake()->create('diagram.png', 40, 'image/png'),
    ])->assertOk()->assertJsonPath('data.url', fn ($url) => $url !== null);

    $spec = ProjectSpec::withoutGlobalScopes()->where('lesson_id', $lesson->id)->first();
    expect($spec->architecture_source->value)->toBe('upload');
    Storage::disk('s3')->assertExists($spec->architecture_path);
});

it('rejects a non-image/pdf architecture upload', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aProjectLesson($this->tenant);
    Sanctum::actingAs(aProjectAdmin($this->tenant));
    saveSpec($lesson);

    postJson("/api/v1/admin/lessons/{$lesson->id}/project/upload", [
        'file' => UploadedFile::fake()->create('evil.exe', 10),
    ])->assertStatus(422);
});

it('refuses a project on a non-project lesson', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aProjectLesson($this->tenant);
    $lesson->update(['type' => 'notes']);
    Sanctum::actingAs(aProjectAdmin($this->tenant));

    putJson("/api/v1/admin/lessons/{$lesson->id}/project", ['title' => 'X'])->assertStatus(422);
});

it('denies project management without manage-curriculum', function () {
    $this->seed(RolePermissionSeeder::class);
    $lesson = aProjectLesson($this->tenant);
    Sanctum::actingAs(counselorIn($this->tenant));

    putJson("/api/v1/admin/lessons/{$lesson->id}/project", ['title' => 'X'])->assertForbidden();
});

it('cannot manage another tenant project (cross-tenant denial)', function () {
    $this->seed(RolePermissionSeeder::class);
    $foreign = aProjectLesson(Tenant::factory()->create());
    Sanctum::actingAs(aProjectAdmin($this->tenant));

    putJson("/api/v1/admin/lessons/{$foreign->id}/project", ['title' => 'X'])->assertNotFound();
});

/* ---- Student surface ---- */

it('gives the student the brief, stack, architecture and a download link', function () {
    $lesson = aProjectLesson($this->tenant);
    withinTenant($this->tenant, fn () => ProjectSpec::query()->create([
        'tenant_id' => $this->tenant->id, 'lesson_id' => $lesson->id,
        'title' => 'Pipeline', 'summary' => 'Build it.', 'tech_stack' => ['Kafka'],
        'architecture' => json_decode(ARCH_JSON, true),
        'architecture_source' => 'ai', 'architecture_path' => 'projects/x/lesson.pdf',
    ]));
    Sanctum::actingAs($this->student);

    $res = getJson("/api/v1/me/lessons/{$lesson->id}/project")
        ->assertOk()
        ->assertJsonPath('data.title', 'Pipeline')
        ->assertJsonPath('data.tech_stack', ['Kafka'])
        ->assertJsonPath('data.architecture.layers.0.name', 'Ingestion');

    expect($res->json('data.download_url'))->toContain('projects/x/lesson.pdf');
});

it('404s a missing or other-tenant project for the student', function () {
    $lesson = aProjectLesson($this->tenant);
    Sanctum::actingAs($this->student);
    getJson("/api/v1/me/lessons/{$lesson->id}/project")->assertNotFound();

    withinTenant($this->tenant, fn () => ProjectSpec::query()->create([
        'tenant_id' => $this->tenant->id, 'lesson_id' => $lesson->id, 'title' => 'P',
    ]));
    $intruder = User::factory()->for(Tenant::factory()->create())->create(['user_type' => 'student']);
    Sanctum::actingAs($intruder);
    getJson("/api/v1/me/lessons/{$lesson->id}/project")->assertNotFound();
});

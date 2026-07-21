<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin);
});

function csvFile(string $body): UploadedFile
{
    return UploadedFile::fake()->createWithContent('syllabus.csv', $body);
}

$SAMPLE = <<<'CSV'
program,course,module,topic,lesson_title,lesson_type
Data Engineering,DE Bootcamp,ETL Foundations,Batch vs Streaming,Intro to ETL,notes
Data Engineering,DE Bootcamp,ETL Foundations,Batch vs Streaming,ETL walkthrough,video
Data Engineering,DE Bootcamp,Spark,RDDs,Spark basics,notes
CSV;

it('downloads a CSV template with the expected headers', function () {
    $res = get('/api/v1/admin/syllabus/template')->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv');
    expect($res->getContent())->toContain('program,course,module,topic,lesson_title,lesson_type');
});

it('builds the whole tree from a CSV in one upload', function () use ($SAMPLE) {
    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($SAMPLE)])
        ->assertCreated()
        ->assertJsonPath('data.programs', 1)
        ->assertJsonPath('data.courses', 1)
        ->assertJsonPath('data.modules', 2)
        ->assertJsonPath('data.topics', 2);

    expect(Program::query()->where('name', 'Data Engineering')->exists())->toBeTrue();
    $course = Course::query()->where('name', 'DE Bootcamp')->firstOrFail();
    expect($course->modules()->count())->toBe(2);
});

it('scaffolds Quiz + Assignment + Mock under every topic', function () use ($SAMPLE) {
    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($SAMPLE)])->assertCreated();

    $topic = Topic::query()->where('name', 'Batch vs Streaming')->firstOrFail();
    $types = $topic->lessons()->pluck('type')->map(fn ($t) => $t instanceof LessonType ? $t->value : $t);

    expect($types)->toContain(LessonType::Quiz->value)
        ->toContain(LessonType::Assignment->value)
        ->toContain(LessonType::MockMilestone->value)
        ->toContain(LessonType::Notes->value)   // the explicit lessons too
        ->toContain(LessonType::Video->value);
});

it('is idempotent — re-importing the same CSV creates nothing new', function () use ($SAMPLE) {
    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($SAMPLE)])->assertCreated();
    $before = Lesson::query()->count();

    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($SAMPLE)])
        ->assertCreated()
        ->assertJsonPath('data.topics', 0)
        ->assertJsonPath('data.lessons', 0);

    expect(Lesson::query()->count())->toBe($before);
});

it('rejects a CSV with an invalid lesson_type, importing nothing', function () {
    $bad = "program,course,module,topic,lesson_title,lesson_type\nP,C,M,T,A lesson,banana\n";

    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($bad)])
        ->assertStatus(422)->assertJsonValidationErrorFor('file');

    expect(Program::query()->count())->toBe(0);
});

it('rejects a CSV missing required columns', function () {
    $bad = "program,course,module\nP,C,M\n";

    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($bad)])
        ->assertStatus(422)->assertJsonValidationErrorFor('file');
});

it('imports only into the acting tenant (isolation)', function () use ($SAMPLE) {
    $other = Tenant::factory()->create();

    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($SAMPLE)])->assertCreated();

    // Every created row belongs to the acting tenant; the other tenant is untouched.
    $foreignPrograms = Program::query()->withoutGlobalScopes()->where('tenant_id', $other->id)->count();
    $minePrograms = Program::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'Data Engineering')->count();
    expect($foreignPrograms)->toBe(0)->and($minePrograms)->toBe(1);
    expect(Course::query()->withoutGlobalScopes()->where('tenant_id', $other->id)->count())->toBe(0);
});

it('denies staff without manage-curriculum', function () use ($SAMPLE) {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');
    Sanctum::actingAs($mentor);

    get('/api/v1/admin/syllabus/template')->assertForbidden();
    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($SAMPLE)])->assertForbidden();
});

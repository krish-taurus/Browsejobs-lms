<?php

declare(strict_types=1);

use App\Models\Assignment;
use App\Models\AssignmentGrade;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
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

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

function assignmentFor(Tenant $tenant): Assignment
{
    $course = Course::factory()->for($tenant)->create();
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);
    $lesson = Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'assignment']);

    return Assignment::factory()->for($tenant)->create(['lesson_id' => $lesson->id]);
}

it('lists course assignments with the student\'s own status', function () {
    $assignment = assignmentFor($this->tenant);
    $courseId = $assignment->lesson->topic->module->course_id;
    $batch = Batch::factory()->for($this->tenant)->create(['course_id' => $courseId]);
    BatchMember::factory()->for($this->tenant)->create([
        'batch_id' => $batch->id, 'user_id' => $this->student->id, 'status' => 'enrolled',
    ]);

    // A second assignment the student has submitted, with a released grade.
    $graded = assignmentFor($this->tenant);
    $gradedCourse = $graded->lesson->topic->module->course_id;
    $batch2 = Batch::factory()->for($this->tenant)->create(['course_id' => $gradedCourse]);
    BatchMember::factory()->for($this->tenant)->create([
        'batch_id' => $batch2->id, 'user_id' => $this->student->id, 'status' => 'enrolled',
    ]);
    $submission = AssignmentSubmission::factory()->for($this->tenant)->create([
        'assignment_id' => $graded->id, 'user_id' => $this->student->id, 'status' => 'submitted', 'submitted_at' => now(),
    ]);
    AssignmentGrade::factory()->for($this->tenant)->create([
        'submission_id' => $submission->id, 'status' => 'approved', 'score' => 78, 'max_points' => 100, 'approved_at' => now(),
    ]);

    Sanctum::actingAs($this->student);

    $data = getJson('/api/v1/me/assignments')->assertOk()->json('data');

    $byTitle = collect($data)->keyBy('title');
    expect($byTitle[$assignment->title]['status'])->toBe('open')
        ->and($byTitle[$assignment->title]['score'])->toBeNull()
        ->and($byTitle[$graded->title]['status'])->toBe('graded')
        ->and($byTitle[$graded->title]['score'])->toBe(78);
});

it('does not list another tenant\'s assignments', function () {
    $foreign = assignmentFor(Tenant::factory()->create());
    Sanctum::actingAs($this->student);

    $titles = getJson('/api/v1/me/assignments')->assertOk()->json('data.*.title');

    expect($titles)->not->toContain($foreign->title);
});

it('accepts a submission with text, link, and a file, and queues grading', function () {
    Storage::fake('s3');
    $assignment = assignmentFor($this->tenant);
    $this->fake->reply = 'not-json'; // grading runs (sync queue) but yields no grade — fine here
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/assignments/{$assignment->lesson_id}/submit", [
        'body' => 'My solution explanation.',
        'link' => 'https://github.com/me/repo',
        'file' => UploadedFile::fake()->create('report.pdf', 40, 'application/pdf'),
    ])->assertCreated();

    $submission = AssignmentSubmission::withoutGlobalScopes()->first();
    expect($submission->body)->toBe('My solution explanation.')
        ->and($submission->file_path)->not->toBeNull();
    Storage::disk('s3')->assertExists($submission->file_path);
});

it('requires at least one of body, link, or file', function () {
    $assignment = assignmentFor($this->tenant);
    Sanctum::actingAs($this->student);

    postJson("/api/v1/me/assignments/{$assignment->lesson_id}/submit", [])->assertStatus(422);
});

it('hides a draft grade and NEVER exposes ai_likelihood to the student', function () {
    $assignment = assignmentFor($this->tenant);
    $submission = AssignmentSubmission::factory()->for($this->tenant)->create(['assignment_id' => $assignment->id, 'user_id' => $this->student->id]);
    AssignmentGrade::factory()->for($this->tenant)->create(['submission_id' => $submission->id, 'ai_likelihood' => 88]); // draft
    Sanctum::actingAs($this->student);

    $res = getJson("/api/v1/me/assignments/{$assignment->lesson_id}")->assertOk()->assertJsonPath('data.grade', null);

    // The serialized student payload must never contain the AI-likelihood signal.
    expect($res->getContent())->not->toContain('ai_likelihood')
        ->and($res->getContent())->not->toContain('88');
});

it('reveals a released grade to the student, still without ai_likelihood', function () {
    $assignment = assignmentFor($this->tenant);
    $submission = AssignmentSubmission::factory()->for($this->tenant)->graded()->create(['assignment_id' => $assignment->id, 'user_id' => $this->student->id]);
    AssignmentGrade::factory()->for($this->tenant)->approved()->create(['submission_id' => $submission->id, 'score' => 77, 'ai_likelihood' => 90]);
    Sanctum::actingAs($this->student);

    $res = getJson("/api/v1/me/assignments/{$assignment->lesson_id}")
        ->assertOk()
        ->assertJsonPath('data.grade.score', 77);

    expect($res->getContent())->not->toContain('ai_likelihood');
});

it('rejects the assignment for guests and other tenants', function () {
    $assignment = assignmentFor($this->tenant);

    getJson("/api/v1/me/assignments/{$assignment->lesson_id}")->assertUnauthorized();

    $intruder = User::factory()->for(Tenant::factory()->create())->create(['user_type' => 'student']);
    Sanctum::actingAs($intruder);
    getJson("/api/v1/me/assignments/{$assignment->lesson_id}")->assertNotFound();
});

/* ---- Admin grading surface ---- */

it('shows the trainer the ai_likelihood signal', function () {
    $this->seed(RolePermissionSeeder::class);
    $assignment = assignmentFor($this->tenant);
    $submission = AssignmentSubmission::factory()->for($this->tenant)->create(['assignment_id' => $assignment->id, 'user_id' => $this->student->id]);
    AssignmentGrade::factory()->for($this->tenant)->create(['submission_id' => $submission->id, 'ai_likelihood' => 42]);

    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    getJson("/api/v1/admin/submissions/{$submission->id}")
        ->assertOk()
        ->assertJsonPath('data.grade.ai_likelihood', 42);
});

it('lets a trainer edit and release a grade, audited', function () {
    $this->seed(RolePermissionSeeder::class);
    $assignment = assignmentFor($this->tenant);
    $submission = AssignmentSubmission::factory()->for($this->tenant)->create(['assignment_id' => $assignment->id, 'user_id' => $this->student->id]);
    $grade = AssignmentGrade::factory()->for($this->tenant)->create(['submission_id' => $submission->id]);

    $trainer = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $trainer->assignRole('trainer');
    Sanctum::actingAs($trainer);

    $this->patchJson("/api/v1/admin/grades/{$grade->id}", [
        'feedback' => 'Revised feedback.',
        'criteria' => [['criterion' => 'Correctness', 'score' => 999, 'note' => 'clamps']],
    ])->assertOk();

    // Score re-clamped to the rubric max (50) for Correctness.
    expect($grade->fresh()->score)->toBe(50)
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'grade.updated')->count())->toBe(1);

    postJson("/api/v1/admin/grades/{$grade->id}/release", [])->assertOk()->assertJsonPath('data.status', 'approved');
});

it('denies grading without manage-curriculum', function () {
    $this->seed(RolePermissionSeeder::class);
    $counselor = counselorIn($this->tenant);
    Sanctum::actingAs($counselor);

    getJson('/api/v1/admin/assignment-submissions')->assertForbidden();
});

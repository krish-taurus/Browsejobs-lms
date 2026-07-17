<?php

declare(strict_types=1);

use App\Actions\Assignments\GradeSubmission;
use App\Actions\Assignments\ReleaseGrade;
use App\Enums\GradeStatus;
use App\Models\AiEvent;
use App\Models\Assignment;
use App\Models\AssignmentGrade;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;

beforeEach(function () {
    $this->fake = new FakeAiClient;
    app()->instance(AiClient::class, $this->fake);
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->tenant = Tenant::factory()->create();
});

/**
 * A submission by an enrolled student on an assignment whose course has a batch trainer.
 *
 * @return array{submission: AssignmentSubmission, trainer: User, student: User}
 */
function gradingFixture(Tenant $tenant): array
{
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);
    $trainer = User::factory()->for($tenant)->create(['user_type' => 'staff']);
    $course = Course::factory()->for($tenant)->create();
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);
    $lesson = Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'assignment']);
    $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id, 'trainer_id' => $trainer->id]);
    BatchMember::factory()->for($tenant)->create(['batch_id' => $batch->id, 'user_id' => $student->id, 'status' => 'enrolled']);
    $assignment = Assignment::factory()->for($tenant)->create(['lesson_id' => $lesson->id]);
    $submission = AssignmentSubmission::factory()->for($tenant)->create(['assignment_id' => $assignment->id, 'user_id' => $student->id]);

    return ['submission' => $submission, 'trainer' => $trainer, 'student' => $student];
}

function gradingReply(): string
{
    return 'draft: '.json_encode([
        'feedback' => 'Good work.',
        'criteria' => [
            ['criterion' => 'Correctness', 'score' => 80, 'note' => 'over max, clamps to 50'],
            ['criterion' => 'Code quality', 'score' => 25, 'note' => 'readable'],
            ['criterion' => 'Documentation', 'score' => 15, 'note' => 'add readme'],
        ],
        'ai_likelihood' => 35,
    ]);
}

it('grades against the rubric, clamping scores, billing the trainer', function () {
    ['submission' => $submission, 'trainer' => $trainer, 'student' => $student] = gradingFixture($this->tenant);
    $this->fake->reply = gradingReply();

    $grade = app(GradeSubmission::class)->handle($submission);

    // 50 (clamped from 80) + 25 + 15 = 90.
    expect($grade->score)->toBe(90)
        ->and($grade->status)->toBe(GradeStatus::Draft)
        ->and($grade->ai_likelihood)->toBe(35);

    // Billed to the trainer, not the student.
    $event = AiEvent::withoutGlobalScopes()->find($grade->ai_event_id);
    expect($event->user_id)->toBe($trainer->id)
        ->and(AiEvent::withoutGlobalScopes()->where('user_id', $student->id)->where('purpose', 'grading')->exists())->toBeFalse();
});

it('falls back to billing the student when there is no batch trainer', function () {
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $course = Course::factory()->for($this->tenant)->create();
    $module = Module::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($this->tenant)->create(['module_id' => $module->id]);
    $lesson = Lesson::factory()->for($this->tenant)->create(['topic_id' => $topic->id, 'type' => 'assignment']);
    $assignment = Assignment::factory()->for($this->tenant)->create(['lesson_id' => $lesson->id]);
    $submission = AssignmentSubmission::factory()->for($this->tenant)->create(['assignment_id' => $assignment->id, 'user_id' => $student->id]);
    $this->fake->reply = gradingReply();

    $grade = app(GradeSubmission::class)->handle($submission);

    expect(AiEvent::withoutGlobalScopes()->find($grade->ai_event_id)->user_id)->toBe($student->id);
});

it('produces no grade when the AI output is unparseable', function () {
    ['submission' => $submission] = gradingFixture($this->tenant);
    $this->fake->reply = 'I cannot grade this.';

    expect(app(GradeSubmission::class)->handle($submission))->toBeNull()
        ->and(AssignmentGrade::withoutGlobalScopes()->count())->toBe(0);
});

it('does not re-grade a submission whose grade is already released', function () {
    ['submission' => $submission] = gradingFixture($this->tenant);
    AssignmentGrade::factory()->for($this->tenant)->approved()->create(['submission_id' => $submission->id, 'score' => 42]);
    $this->fake->reply = gradingReply();

    // The guard short-circuits; the released grade is untouched.
    expect(app(GradeSubmission::class)->handle($submission))->toBeNull()
        ->and($submission->grade()->first()->score)->toBe(42);
});

it('releases a grade: audits it and notifies the student', function () {
    ['submission' => $submission, 'trainer' => $trainer] = gradingFixture($this->tenant);
    MessageTemplate::factory()->for($this->tenant)->create(['key' => 'grade_ready', 'channel' => 'whatsapp', 'body' => 'Hi {{name}}, {{score}}/{{max}}: {{link}}']);
    $grade = AssignmentGrade::factory()->for($this->tenant)->create(['submission_id' => $submission->id]);

    app(ReleaseGrade::class)->handle($grade, $trainer);

    expect($grade->fresh()->status)->toBe(GradeStatus::Approved)
        ->and($submission->fresh()->status->value)->toBe('graded')
        ->and(AuditLog::withoutGlobalScopes()->where('action', 'grade.released')->count())->toBe(1)
        ->and(Message::withoutGlobalScopes()->where('template_key', 'grade_ready')->count())->toBe(1);
});

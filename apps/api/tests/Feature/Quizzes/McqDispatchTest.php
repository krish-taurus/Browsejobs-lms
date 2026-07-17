<?php

declare(strict_types=1);

use App\Actions\Crm\CreateTask;
use App\Events\ModuleCompleted;
use App\Jobs\CheckQuizCompletion;
use App\Models\Course;
use App\Models\CrmTask;
use App\Models\Lead;
use App\Models\Lesson;
use App\Models\MagicLink;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Support\Messaging\Messenger;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->wa = new FakeWhatsAppClient;
    app()->instance(WhatsAppClient::class, $this->wa);
    $this->tenant = Tenant::factory()->create();
    MessageTemplate::factory()->for($this->tenant)->create(['key' => 'mcq_dispatch', 'channel' => 'whatsapp', 'body' => 'Hi {{name}}, quiz for {{module}}: {{link}}']);
    MessageTemplate::factory()->for($this->tenant)->create(['key' => 'mcq_reminder', 'channel' => 'whatsapp', 'body' => 'Reminder {{name}}: {{link}}']);
});

/**
 * @return array{module: Module, quiz: Quiz, student: User}
 */
function approvedModuleQuiz(Tenant $tenant, bool $approved = true): array
{
    $student = User::factory()->for($tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 '.fake()->numberBetween(10000, 99999)]);
    $course = Course::factory()->for($tenant)->create();
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);
    $lesson = Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'quiz']);
    $quiz = Quiz::factory()->for($tenant)->create(['lesson_id' => $lesson->id, 'status' => $approved ? 'approved' : 'draft', 'approved_at' => $approved ? now() : null]);
    QuizQuestion::factory()->for($tenant)->create(['quiz_id' => $quiz->id]);

    return ['module' => $module, 'quiz' => $quiz, 'student' => $student];
}

it('dispatches an approved module quiz with a magic link, once', function () {
    ['module' => $module, 'quiz' => $quiz, 'student' => $student] = approvedModuleQuiz($this->tenant);

    ModuleCompleted::dispatch($student, $module);

    expect(QuizAttempt::withoutGlobalScopes()->where('quiz_id', $quiz->id)->count())->toBe(1)
        ->and(Message::withoutGlobalScopes()->where('template_key', 'mcq_dispatch')->count())->toBe(1)
        ->and(MagicLink::withoutGlobalScopes()->where('action', 'mcq.attempt')->count())->toBe(1);

    // Re-firing does not double-dispatch.
    ModuleCompleted::dispatch($student, $module);
    expect(QuizAttempt::withoutGlobalScopes()->where('quiz_id', $quiz->id)->count())->toBe(1)
        ->and(Message::withoutGlobalScopes()->where('template_key', 'mcq_dispatch')->count())->toBe(1);
});

it('does not dispatch a draft (unapproved) quiz', function () {
    ['module' => $module] = approvedModuleQuiz($this->tenant, approved: false);
    $student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);

    ModuleCompleted::dispatch($student, $module);

    expect(QuizAttempt::withoutGlobalScopes()->count())->toBe(0)
        ->and(Message::withoutGlobalScopes()->where('template_key', 'mcq_dispatch')->count())->toBe(0);
});

it('sends a 48h reminder then a 96h counselor flag, idempotently', function () {
    Carbon::setTestNow('2026-07-17 09:00:00');
    ['quiz' => $quiz, 'student' => $student] = approvedModuleQuiz($this->tenant);
    $attempt = QuizAttempt::factory()->for($this->tenant)->create(['quiz_id' => $quiz->id, 'user_id' => $student->id, 'status' => 'pending']);

    // A CRM lead + assignee so the 96h flag has a target.
    $counselor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    Lead::factory()->for($this->tenant)->create(['email' => $student->email, 'assigned_to' => $counselor->id]);

    // Before 48h → nothing.
    (new CheckQuizCompletion($attempt->id, 'reminder'))->handle(app(Messenger::class), app(CreateTask::class));
    expect($attempt->fresh()->reminded_at)->toBeNull();

    // After 48h → reminder once.
    Carbon::setTestNow('2026-07-19 10:00:00');
    (new CheckQuizCompletion($attempt->id, 'reminder'))->handle(app(Messenger::class), app(CreateTask::class));
    (new CheckQuizCompletion($attempt->id, 'reminder'))->handle(app(Messenger::class), app(CreateTask::class));
    expect($attempt->fresh()->reminded_at)->not->toBeNull()
        ->and(Message::withoutGlobalScopes()->where('template_key', 'mcq_reminder')->count())->toBe(1);

    // After 96h → counselor flag task once.
    Carbon::setTestNow('2026-07-21 10:00:00');
    (new CheckQuizCompletion($attempt->id, 'flag'))->handle(app(Messenger::class), app(CreateTask::class));
    expect($attempt->fresh()->flagged_at)->not->toBeNull()
        ->and(CrmTask::withoutGlobalScopes()->where('source', CrmTask::SOURCE_QUIZ)->count())->toBe(1);

    Carbon::setTestNow();
});

it('does not remind a student who already submitted', function () {
    Carbon::setTestNow('2026-07-19 10:00:00');
    ['quiz' => $quiz, 'student' => $student] = approvedModuleQuiz($this->tenant);
    $attempt = QuizAttempt::factory()->for($this->tenant)->submitted()->create([
        'quiz_id' => $quiz->id, 'user_id' => $student->id, 'created_at' => now()->subDays(3),
    ]);

    (new CheckQuizCompletion($attempt->id, 'reminder'))->handle(app(Messenger::class), app(CreateTask::class));

    expect(Message::withoutGlobalScopes()->where('template_key', 'mcq_reminder')->count())->toBe(0);
    Carbon::setTestNow();
});

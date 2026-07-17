<?php

declare(strict_types=1);

use App\Actions\LiveClasses\RecordAttendance;
use App\Actions\Quizzes\SubmitQuizAttempt;
use App\Actions\Telemetry\RecordActivity;
use App\Enums\ActivityType;
use App\Enums\PointsSource;
use App\Models\ActivityEvent;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\InAppNotification;
use App\Models\Lesson;
use App\Models\LiveSession;
use App\Models\Module;
use App\Models\PointsEvent;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\StudentBadge;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use App\Support\Points\PointsService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

function pointsFor(User $student, PointsSource $source): int
{
    return (int) PointsEvent::withoutGlobalScopes()
        ->where('user_id', $student->id)
        ->where('source', $source->value)
        ->sum('points');
}

/* ---------------------------------------------------------------- attendance */

function sessionFixture(Tenant $tenant): LiveSession
{
    $course = Course::factory()->for($tenant)->create();
    $batch = Batch::factory()->for($tenant)->create(['course_id' => $course->id]);

    return LiveSession::query()->create([
        'tenant_id' => $tenant->id,
        'batch_id' => $batch->id,
        'title' => 'Class',
        'scheduled_start' => now()->subHour(),
        'scheduled_end' => now(),
    ]);
}

it('awards attendance + punctuality when an on-time student attends enough', function () {
    $session = sessionFixture($this->tenant);
    $record = app(RecordAttendance::class);

    // Joined at start (on time), stayed the full hour.
    $record->participantJoined($session, $this->student, now()->subHour());
    $record->participantLeft($session, $this->student, now());

    expect(pointsFor($this->student, PointsSource::Attendance))->toBe(20)
        ->and(pointsFor($this->student, PointsSource::Punctuality))->toBe(5);
});

it('awards attendance but not punctuality to a late joiner', function () {
    $session = sessionFixture($this->tenant);
    $record = app(RecordAttendance::class);

    // Joined 20 min late (grace is 10), stayed the rest.
    $record->participantJoined($session, $this->student, now()->subMinutes(40));
    $record->participantLeft($session, $this->student, now());

    expect(pointsFor($this->student, PointsSource::Attendance))->toBe(20)
        ->and(pointsFor($this->student, PointsSource::Punctuality))->toBe(0);
});

it('awards nothing below the attendance threshold and never double-awards a session', function () {
    $session = sessionFixture($this->tenant);
    $record = app(RecordAttendance::class);

    // Only 12 minutes of a 60-minute class (< 60% default threshold).
    $record->participantJoined($session, $this->student, now()->subHour());
    $record->participantLeft($session, $this->student, now()->subMinutes(48));

    expect(pointsFor($this->student, PointsSource::Attendance))->toBe(0);

    // Rejoins and crosses the threshold — single award despite two cycles.
    $record->participantJoined($session, $this->student, now()->subMinutes(45));
    $record->participantLeft($session, $this->student, now());
    $record->participantJoined($session, $this->student, now());
    $record->participantLeft($session, $this->student, now());

    expect(pointsFor($this->student, PointsSource::Attendance))->toBe(20)
        ->and(PointsEvent::withoutGlobalScopes()->where('source', 'attendance')->count())->toBe(1);
});

/* --------------------------------------------------------------------- quiz */

/**
 * @return array{attempt: QuizAttempt, right1: int, right2: int}
 */
function quizFixture(Tenant $tenant, User $student): array
{
    $course = Course::factory()->for($tenant)->create();
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);
    $lesson = Lesson::factory()->for($tenant)->create(['topic_id' => $topic->id, 'type' => 'quiz']);
    $quiz = Quiz::factory()->for($tenant)->approved()->create(['lesson_id' => $lesson->id, 'shuffle' => false, 'pass_pct' => 60]);
    $q1 = QuizQuestion::factory()->for($tenant)->create(['quiz_id' => $quiz->id, 'correct_index' => 1, 'position' => 0]);
    $q2 = QuizQuestion::factory()->for($tenant)->create(['quiz_id' => $quiz->id, 'correct_index' => 2, 'position' => 1]);

    $attempt = QuizAttempt::factory()->for($tenant)->create([
        'quiz_id' => $quiz->id, 'user_id' => $student->id, 'status' => 'pending',
    ]);

    return ['attempt' => $attempt, 'right1' => $q1->id, 'right2' => $q2->id];
}

it('awards quiz points on a pass, the module-ace badge at 100%, and telemetry', function () {
    $fx = quizFixture($this->tenant, $this->student);

    app(SubmitQuizAttempt::class)->handle($fx['attempt'], [
        (string) $fx['right1'] => 1,
        (string) $fx['right2'] => 2,
    ], []);

    expect(pointsFor($this->student, PointsSource::Quiz))->toBe(30)
        ->and(StudentBadge::withoutGlobalScopes()->where('badge', 'module-ace')->where('user_id', $this->student->id)->exists())->toBeTrue()
        ->and(ActivityEvent::withoutGlobalScopes()->where('type', ActivityType::QuizSubmitted->value)->count())->toBe(1)
        ->and(InAppNotification::withoutGlobalScopes()->where('user_id', $this->student->id)->where('title', 'like', '%Module Ace%')->exists())->toBeTrue();
});

it('awards nothing on a failing quiz', function () {
    $fx = quizFixture($this->tenant, $this->student);

    app(SubmitQuizAttempt::class)->handle($fx['attempt'], [
        (string) $fx['right1'] => 0, // wrong
        (string) $fx['right2'] => 0, // wrong
    ], []);

    expect(pointsFor($this->student, PointsSource::Quiz))->toBe(0)
        ->and(StudentBadge::withoutGlobalScopes()->count())->toBe(0);
});

/* ------------------------------------------------------------------- streak */

it('awards streak points on the second consecutive day and the badge at seven', function () {
    // Six consecutive prior days of activity.
    foreach (range(1, 6) as $daysAgo) {
        ActivityEvent::query()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->student->id,
            'type' => ActivityType::Login->value,
            'occurred_at' => now()->subDays($daysAgo),
        ]);
    }

    // Today's first activity arrives through the real seam.
    app(RecordActivity::class)->handle($this->student, ActivityType::Login);

    expect(pointsFor($this->student, PointsSource::Streak))->toBe(10)
        ->and(StudentBadge::withoutGlobalScopes()->where('badge', 'seven-day-streak')->exists())->toBeTrue();

    // A second event today does not double-award.
    app(RecordActivity::class)->handle($this->student, ActivityType::LessonViewed);
    expect(pointsFor($this->student, PointsSource::Streak))->toBe(10);
});

it('awards no streak points on an isolated first day', function () {
    app(RecordActivity::class)->handle($this->student, ActivityType::Login);

    expect(pointsFor($this->student, PointsSource::Streak))->toBe(0);
});

/* ------------------------------------------------------- badge celebrations */

it('celebrates a badge to active batchmates but not when the earner opted out', function () {
    $course = Course::factory()->for($this->tenant)->create();
    $batch = Batch::factory()->for($this->tenant)->create(['course_id' => $course->id]);
    $mate = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    BatchMember::factory()->for($this->tenant)->create(['batch_id' => $batch->id, 'user_id' => $this->student->id, 'status' => 'enrolled']);
    BatchMember::factory()->for($this->tenant)->create(['batch_id' => $batch->id, 'user_id' => $mate->id, 'status' => 'enrolled']);

    app(PointsService::class)->grantBadge($this->student, 'first-lab-pass', $this->tenant->id);

    expect(InAppNotification::withoutGlobalScopes()->where('user_id', $mate->id)->count())->toBe(1);

    // Opted-out earner: mate gets no broadcast for a fresh badge.
    $optedOut = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'leaderboard_opt_out' => true]);
    BatchMember::factory()->for($this->tenant)->create(['batch_id' => $batch->id, 'user_id' => $optedOut->id, 'status' => 'enrolled']);

    app(PointsService::class)->grantBadge($optedOut, 'first-lab-pass', $this->tenant->id);

    expect(InAppNotification::withoutGlobalScopes()->where('user_id', $mate->id)->count())->toBe(1)
        ->and(InAppNotification::withoutGlobalScopes()->where('user_id', $optedOut->id)->count())->toBe(1);
});

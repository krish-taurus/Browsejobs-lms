<?php

declare(strict_types=1);

use App\Actions\Curriculum\MarkTopicCompleted;
use App\Enums\LessonType;
use App\Models\Course;
use App\Models\InAppNotification;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;

beforeEach(function () {
    config(['monetization.text_practice_enabled' => true]);
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

/** A topic that does (or doesn't) carry a mock_milestone lesson. */
function topicWithMilestone(Tenant $tenant, bool $milestone): Topic
{
    $course = Course::factory()->for($tenant)->create();
    $module = Module::factory()->for($tenant)->create(['course_id' => $course->id]);
    $topic = Topic::factory()->for($tenant)->create(['module_id' => $module->id]);
    Lesson::factory()->for($tenant)->create([
        'topic_id' => $topic->id,
        'type' => $milestone ? LessonType::MockMilestone->value : LessonType::Notes->value,
    ]);

    return $topic;
}

function milestoneNotes(User $student): int
{
    return InAppNotification::query()
        ->where('user_id', $student->id)
        ->where('title', 'like', 'Milestone reached%')
        ->count();
}

it('sends the qualifying mock when a milestone topic is completed', function () {
    $topic = topicWithMilestone($this->tenant, true);

    withinTenant($this->tenant, fn () => app(MarkTopicCompleted::class)->handle($this->student, $topic));

    expect(milestoneNotes($this->student))->toBe(1);
});

it('does not fire for a topic without a mock_milestone lesson', function () {
    $topic = topicWithMilestone($this->tenant, false);

    withinTenant($this->tenant, fn () => app(MarkTopicCompleted::class)->handle($this->student, $topic));

    expect(milestoneNotes($this->student))->toBe(0);
});

it('stays silent when text practice is disabled', function () {
    config(['monetization.text_practice_enabled' => false]);
    $topic = topicWithMilestone($this->tenant, true);

    withinTenant($this->tenant, fn () => app(MarkTopicCompleted::class)->handle($this->student, $topic));

    expect(milestoneNotes($this->student))->toBe(0);
});

it('fires once — re-completing the same topic does not re-send', function () {
    $topic = topicWithMilestone($this->tenant, true);

    withinTenant($this->tenant, function () use ($topic) {
        $mark = app(MarkTopicCompleted::class);
        $mark->handle($this->student, $topic);
        $mark->handle($this->student, $topic); // idempotent — TopicCompleted only fires first time
    });

    expect(milestoneNotes($this->student))->toBe(1);
});

<?php

declare(strict_types=1);

namespace App\Actions\Curriculum;

use App\Events\CourseCompleted;
use App\Events\ModuleCompleted;
use App\Events\TopicCompleted;
use App\Models\Course;
use App\Models\Module;
use App\Models\Topic;
use App\Models\TopicCompletion;
use App\Models\User;

/**
 * Records a student's completion of a topic and fires the first-class events.
 * Idempotent: re-marking an already-completed topic does nothing and fires
 * nothing. Completing the last topic of a module fires ModuleCompleted too.
 */
final readonly class MarkTopicCompleted
{
    public function handle(User $user, Topic $topic): TopicCompletion
    {
        $completion = TopicCompletion::query()->firstOrCreate(
            ['user_id' => $user->id, 'topic_id' => $topic->id],
            ['tenant_id' => $topic->tenant_id, 'completed_at' => now()],
        );

        if (! $completion->wasRecentlyCreated) {
            return $completion;
        }

        TopicCompleted::dispatch($user, $topic);

        $this->fireModuleCompletedIfFinished($user, $topic->module);

        return $completion;
    }

    private function fireModuleCompletedIfFinished(User $user, Module $module): void
    {
        $topicIds = $module->topics()->pluck('id');

        if ($topicIds->isEmpty()) {
            return;
        }

        $completed = TopicCompletion::query()
            ->where('user_id', $user->id)
            ->whereIn('topic_id', $topicIds)
            ->count();

        if ($completed === $topicIds->count()) {
            ModuleCompleted::dispatch($user, $module);
            $module->loadMissing('course');
            if ($module->course !== null) {
                $this->fireCourseCompletedIfFinished($user, $module->course);
            }
        }
    }

    private function fireCourseCompletedIfFinished(User $user, Course $course): void
    {
        // Batched: every topic in the course in one query, one completion count.
        $topicIds = Topic::query()
            ->whereIn('module_id', $course->modules()->select('id'))
            ->pluck('id');

        if ($topicIds->isEmpty()) {
            return; // a course with no topics never "completes"
        }

        $completed = TopicCompletion::query()
            ->where('user_id', $user->id)
            ->whereIn('topic_id', $topicIds)
            ->count();

        if ($completed === $topicIds->count()) {
            CourseCompleted::dispatch($user, $course);
        }
    }
}

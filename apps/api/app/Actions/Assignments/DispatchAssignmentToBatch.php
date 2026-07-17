<?php

declare(strict_types=1);

namespace App\Actions\Assignments;

use App\Enums\BatchMemberStatus;
use App\Models\Batch;
use App\Models\InAppNotification;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Sends a specific assignment (a lesson's rubric task) to every active student in a batch
 * (PRD §6.5) — "the assignment for the class I took". Unlike quizzes, assignments had no
 * dispatch path; this is it. In-app notification linking straight to the assignment.
 */
final readonly class DispatchAssignmentToBatch
{
    /** @return int students notified */
    public function handle(Batch $batch, Lesson $lesson, ?User $actor = null): int
    {
        return app(TenantContext::class)->run($batch->tenant, function () use ($batch, $lesson): int {
            $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

            $count = 0;
            foreach ($batch->members()->whereIn('status', $occupying)->with('student')->get() as $member) {
                $student = $member->student;
                if ($student === null) {
                    continue;
                }

                InAppNotification::query()->create([
                    'tenant_id' => $student->tenant_id,
                    'user_id' => $student->id,
                    'title' => "New assignment: {$lesson->title}",
                    'body' => 'Your trainer set an assignment from today\'s class.',
                    'url' => "/assignments/{$lesson->id}",
                ]);

                $count++;
            }

            return $count;
        });
    }
}

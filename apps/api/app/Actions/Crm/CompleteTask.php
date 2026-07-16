<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Models\CrmTask;

/**
 * Marks a CRM task done. Idempotent — completing an already-complete task keeps
 * the original completion time.
 */
final readonly class CompleteTask
{
    public function handle(CrmTask $task): CrmTask
    {
        if ($task->completed_at === null) {
            $task->completed_at = now();
            $task->save();
        }

        return $task;
    }
}

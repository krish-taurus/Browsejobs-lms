<?php

declare(strict_types=1);

namespace App\Actions\Taurus;

use RuntimeException;

/** Approve/reject was attempted on a task that is not waiting for a decision. */
final class TaskNotAwaitingApproval extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This task is not waiting for approval.');
    }
}

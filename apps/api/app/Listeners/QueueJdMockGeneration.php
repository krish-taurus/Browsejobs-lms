<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EmployerJobPublished;
use App\Jobs\GenerateJdMock;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * PRD-E F2 automation: publishing a JD queues generation of its
 * JD-specific mock. Never inline in the request cycle (CLAUDE.md).
 */
final class QueueJdMockGeneration implements ShouldQueue
{
    public function handle(EmployerJobPublished $event): void
    {
        $pending = $event->job->mocks()
            ->withoutGlobalScopes()
            ->where('status', 'pending')
            ->orderByDesc('version')
            ->first();

        if ($pending !== null) {
            GenerateJdMock::dispatch($pending->id);
        }
    }
}

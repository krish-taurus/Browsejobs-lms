<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EmployerJobPublished;
use App\Jobs\RunJobShortlistAgent;
use App\Support\Employers\ShortlistSettings;

/**
 * Hiring pipeline step 2: publishing a JD wakes the shortlist agent, unless
 * the client turned "run on publish" off for this JD. The work itself is a
 * queued job — never inline in the request cycle (CLAUDE.md).
 */
final class QueueShortlistAgent
{
    public function handle(EmployerJobPublished $event): void
    {
        if (ShortlistSettings::forJob($event->job)->autoOnPublish) {
            RunJobShortlistAgent::dispatch($event->job->id);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Employers\RunShortlistAgent;
use App\Enums\EmployerJobStatus;
use App\Models\EmployerJob;
use App\Models\JobShortlistRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the shortlist agent for a JD in the background (on publish).
 * Idempotent: a run only rebuilds the list from current data, and a JD that
 * is no longer published is left alone.
 */
final class RunJobShortlistAgent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $employerJobId) {}

    public function handle(RunShortlistAgent $agent): void
    {
        $job = EmployerJob::withoutGlobalScopes()->find($this->employerJobId);
        if ($job === null || $job->status !== EmployerJobStatus::Published) {
            return;
        }

        $agent->handle($job, JobShortlistRun::TRIGGER_PUBLISH);
    }
}

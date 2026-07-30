<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerJobStatus;
use App\Models\EmployerJob;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * PRD-E F2 — pause/close transitions (publishing goes through
 * PublishEmployerJob, which owns mock creation).
 */
final readonly class ChangeEmployerJobStatus
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(EmployerJob $job, EmployerJobStatus $target, User $actor): EmployerJob
    {
        return app(TenantContext::class)->run($job->tenant, function () use ($job, $target, $actor): EmployerJob {
            if ($target === EmployerJobStatus::Published) {
                throw ValidationException::withMessages([
                    'status' => 'Use the publish endpoint to publish a JD.',
                ]);
            }

            if (! $job->status->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot move a {$job->status->value} JD to {$target->value}.",
                ]);
            }

            $previous = $job->status;
            $job->update(['status' => $target->value]);

            $this->audit->log('employer.job_status_changed', $job, [
                'from' => $previous->value,
                'to' => $target->value,
            ], $actor);

            return $job->fresh();
        });
    }
}

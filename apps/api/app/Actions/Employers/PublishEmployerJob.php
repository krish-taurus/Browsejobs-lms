<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerJobStatus;
use App\Enums\JdMockStatus;
use App\Events\EmployerJobPublished;
use App\Models\EmployerJob;
use App\Models\JdMock;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PRD-E F2 — publish a JD. First publish creates the pending jd_mock v1;
 * the queued listener generates it. Re-publish from paused reuses the
 * existing mock version (scores stay comparable).
 */
final readonly class PublishEmployerJob
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(EmployerJob $job, User $actor): EmployerJob
    {
        return app(TenantContext::class)->run($job->tenant, function () use ($job, $actor): EmployerJob {
            if (! $job->status->canTransitionTo(EmployerJobStatus::Published)) {
                throw ValidationException::withMessages([
                    'status' => "A {$job->status->value} JD cannot be published.",
                ]);
            }

            DB::transaction(function () use ($job): void {
                $job->update([
                    'status' => EmployerJobStatus::Published->value,
                    'published_at' => $job->published_at ?? now(),
                ]);

                if (! $job->mocks()->exists()) {
                    JdMock::create([
                        'employer_job_id' => $job->id,
                        'version' => 1,
                        'status' => JdMockStatus::Pending->value,
                    ]);
                }
            });

            $this->audit->log('employer.job_published', $job, [
                'workspace_id' => $job->employer_workspace_id,
            ], $actor);

            EmployerJobPublished::dispatch($job->fresh());

            return $job->fresh();
        });
    }
}

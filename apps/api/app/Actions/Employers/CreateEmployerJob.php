<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerJobStatus;
use App\Models\EmployerJob;
use App\Models\EmployerWorkspace;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;

/** PRD-E F2 — create a draft JD in a workspace. */
final readonly class CreateEmployerJob
{
    public function __construct(private AuditLogger $audit)
    {
    }

    /** @param array<string, mixed> $attributes */
    public function handle(EmployerWorkspace $workspace, User $creator, array $attributes): EmployerJob
    {
        return app(TenantContext::class)->run($workspace->tenant, function () use ($workspace, $creator, $attributes): EmployerJob {
            $job = EmployerJob::create([
                ...$attributes,
                'employer_workspace_id' => $workspace->id,
                'created_by_id' => $creator->id,
                'status' => EmployerJobStatus::Draft->value,
            ]);

            $this->audit->log('employer.job_created', $job, [
                'workspace_id' => $workspace->id,
                'title' => $job->title,
            ], $creator);

            return $job;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\JdMockStatus;
use App\Jobs\GenerateJdMock;
use App\Models\EmployerJob;
use App\Models\JdMock;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * PRD-E F2 — create and queue the next mock version. Existing ready
 * versions are never mutated (comparability); a pending/generating
 * version blocks a new one.
 */
final readonly class RegenerateJdMock
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function handle(EmployerJob $job, User $actor): JdMock
    {
        return app(TenantContext::class)->run($job->tenant, function () use ($job, $actor): JdMock {
            $inFlight = $job->mocks()
                ->whereIn('status', [JdMockStatus::Pending->value, JdMockStatus::Generating->value])
                ->exists();

            if ($inFlight) {
                throw ValidationException::withMessages([
                    'mock' => 'A mock version is already being generated for this JD.',
                ]);
            }

            $next = ((int) $job->mocks()->max('version')) + 1;

            $mock = JdMock::create([
                'employer_job_id' => $job->id,
                'version' => $next,
                'status' => JdMockStatus::Pending->value,
            ]);

            $this->audit->log('employer.jd_mock_regenerated', $mock, [
                'job_id' => $job->id,
                'version' => $next,
            ], $actor);

            GenerateJdMock::dispatch($mock->id);

            return $mock;
        });
    }
}

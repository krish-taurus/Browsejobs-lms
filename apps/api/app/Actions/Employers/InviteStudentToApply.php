<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Events\StudentInvitedToApply;
use App\Models\EmployerJob;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * PRD-E F7 — an employer invites a matched LMS student to apply.
 *
 * This creates no application: the candidate still chooses to apply and
 * still completes the JD mock. The invitation is a nudge, never an
 * enrolment — which keeps "applying is the candidate's free choice"
 * true, and keeps grading independent of employer interest.
 */
final readonly class InviteStudentToApply
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(EmployerJob $job, User $candidate, User $actor): void
    {
        app(TenantContext::class)->run($job->tenant, function () use ($job, $candidate, $actor): void {
            if ($candidate->tenant_id !== $job->tenant_id) {
                throw ValidationException::withMessages([
                    'candidate' => 'This candidate is not available on this tenant.',
                ]);
            }

            $this->audit->log('employer.student_invited_to_apply', $job, [
                'candidate_id' => $candidate->id,
                'workspace_id' => $job->employer_workspace_id,
            ], $actor);

            StudentInvitedToApply::dispatch($job, $candidate);
        });
    }
}

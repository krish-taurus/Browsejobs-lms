<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Assignments\GradeSubmission;
use App\Models\AssignmentSubmission;
use App\Models\Tenant;
use App\Services\AI\AiBudgetExceeded;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued AI grading of an assignment submission (PRD §6.5; every AI call is a queued
 * job per CLAUDE.md). Idempotent: skips a submission whose grade is already released.
 * A budget/parse failure leaves the submission awaiting manual grade (no crash-loop).
 */
final class GradeSubmissionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $submissionId) {}

    public function handle(GradeSubmission $grade): void
    {
        $submission = AssignmentSubmission::query()->withoutGlobalScopes()->find($this->submissionId);
        if ($submission === null || $submission->hasReleasedGrade()) {
            return;
        }

        $tenant = Tenant::query()->find($submission->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($submission, $grade): void {
            try {
                $grade->handle($submission);
            } catch (AiBudgetExceeded|Throwable $e) {
                // Leave the submission awaiting a manual grade; the trainer can grade by hand.
            }
        });
    }
}

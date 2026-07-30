<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\EmployerApplicationStage;
use App\Events\ApplicationStageChanged;
use App\Models\InAppNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Tell the candidate when an employer moves them (PRD-E F11).
 *
 * Silence is the worst part of applying for a job, so every stage an
 * employer sets is surfaced — including a rejection. Rejections are worded
 * plainly rather than softened into ambiguity: a candidate who cannot tell
 * whether they are still in a process cannot plan around it.
 *
 * `Graded` is skipped: it is assigned by the system the moment a mock is
 * scored, and the candidate already saw that score.
 */
final class NotifyCandidateOfStageChange implements ShouldQueue
{
    public function handle(ApplicationStageChanged $event): void
    {
        $application = $event->application;
        $stage = $application->stage instanceof EmployerApplicationStage
            ? $application->stage
            : EmployerApplicationStage::tryFrom((string) $application->stage);

        if ($stage === null || $stage === EmployerApplicationStage::Graded) {
            return;
        }

        $application->loadMissing('job.workspace');
        $role = $application->job?->title ?? 'a role';
        $company = $application->job?->workspace?->name ?? 'the hiring team';

        $copy = $this->copyFor($stage, $role, $company);

        if ($copy === null) {
            return;
        }

        app(TenantContext::class)->run($application->tenant, function () use ($application, $copy): void {
            InAppNotification::query()->create([
                'tenant_id' => $application->tenant_id,
                'user_id' => $application->candidate_id,
                'title' => $copy['title'],
                'body' => $copy['body'],
                'url' => '/dashboard/applications',
            ]);
        });
    }

    /**
     * @return array{title: string, body: string}|null
     */
    private function copyFor(EmployerApplicationStage $stage, string $role, string $company): ?array
    {
        return match ($stage) {
            EmployerApplicationStage::Shortlisted => [
                'title' => "Shortlisted for {$role}",
                'body' => "{$company} shortlisted you. They can now see your contact details.",
            ],
            EmployerApplicationStage::L1 => [
                'title' => "Round 1 invite — {$role}",
                'body' => "{$company} invited you to the first interview round. It runs here on BrowseJobs.",
            ],
            EmployerApplicationStage::L2 => [
                'title' => "Round 2 invite — {$role}",
                'body' => "{$company} moved you to the second round.",
            ],
            EmployerApplicationStage::HumanRound => [
                'title' => "Live round — {$role}",
                'body' => "{$company} wants to meet you directly. Details follow by email.",
            ],
            EmployerApplicationStage::Offer => [
                'title' => "Offer from {$company}",
                'body' => "{$company} made you an offer for {$role}. Open your applications to see the terms.",
            ],
            EmployerApplicationStage::Hired => [
                'title' => "You're hired — {$role}",
                'body' => "{$company} marked you as hired. Congratulations.",
            ],
            EmployerApplicationStage::Rejected => [
                'title' => "Not moving forward — {$role}",
                'body' => "{$company} closed your application for {$role}. Your mock score and feedback stay yours, and they carry to your next application.",
            ],
            default => null,
        };
    }
}

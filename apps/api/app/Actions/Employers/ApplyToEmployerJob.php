<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Actions\Cv\GenerateCv;
use App\Enums\EmployerApplicationStage;
use App\Enums\EmployerJobStatus;
use App\Events\ApplicationReceived;
use App\Models\CvDocument;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\MockInterview;
use App\Models\User;
use App\Support\Entitlements\ActiveCareerBoost;
use App\Support\Entitlements\EntitlementService;
use App\Support\JobFeed\RelevanceScorer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * PRD-E F3 — a candidate applies to a published JD.
 *
 * Applying is free, but no longer the first step: a CV on file and a
 * completed, scored voice interview for this exact JD are both required
 * before the Apply action is even offered (see StartEmployerJobMock). This
 * action is the last gate, not just record-keeping — it re-checks both,
 * because the frontend hiding a button is not the same as the backend
 * enforcing the rule.
 *
 * @see RecordApplicationGrade
 */
final readonly class ApplyToEmployerJob
{
    public function __construct(
        private GenerateCv $cv,
        private RecordApplicationGrade $grade,
        private EntitlementService $entitlements,
        private ActiveCareerBoost $boost,
        private RelevanceScorer $relevance,
    ) {}

    /** @param array<int, array<string, mixed>>|null $knockoutAnswers */
    public function handle(EmployerJob $job, User $candidate, ?array $knockoutAnswers = null): EmployerJobApplication
    {
        return app(TenantContext::class)->run($candidate->tenant, function () use ($job, $candidate, $knockoutAnswers): EmployerJobApplication {
            if ($job->status !== EmployerJobStatus::Published) {
                throw ValidationException::withMessages([
                    'job' => 'This role is not open for applications.',
                ]);
            }

            $exists = EmployerJobApplication::where('employer_job_id', $job->id)
                ->where('candidate_id', $candidate->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'job' => 'You have already applied to this role.',
                ]);
            }

            // Free-tier ceiling on job applications (CRM-editable). A candidate
            // already on their Nth application here is retrying/resuming, not
            // opening a new one, so the cap only bites on a genuinely new job.
            $limit = $this->entitlements->settings()->free_job_application_limit;
            $applied = EmployerJobApplication::where('candidate_id', $candidate->id)->count();
            if ($applied >= $limit && ! $this->boost->consumeApplication($candidate)) {
                throw ValidationException::withMessages([
                    'limit' => "You've reached the free limit of {$limit} job applications. A Career Boost pack in the Store adds more.",
                ]);
            }

            if (! CvDocument::query()->where('user_id', $candidate->id)->exists()) {
                throw ValidationException::withMessages([
                    'cv' => 'Build your CV before applying — go to My CV.',
                ]);
            }

            $completedInterview = MockInterview::query()
                ->where('user_id', $candidate->id)
                ->where('status', MockInterview::STATUS_COMPLETED)
                ->whereHas('blueprint', fn ($q) => $q->where('employer_job_id', $job->id))
                ->orderByDesc('overall_score')
                ->first();

            if ($completedInterview === null) {
                throw ValidationException::withMessages([
                    'mock' => 'Take this role\'s AI interview before applying.',
                ]);
            }

            // A CV rebuilt against this exact JD, free — the same courtesy an
            // external application already gets.
            $document = $this->cv->handle($candidate, CvDocument::SOURCE_TAILORED, $job->description);

            // How well this candidate's CV overlaps this JD's own skill list —
            // stored alongside the interview score so auto-shortlisting can
            // gate on both, not just whoever happened to talk a good interview
            // off a thin or mismatched CV.
            $cvMatchPct = $this->relevance->scoreForEmployerJob($candidate, $job)['match_pct'];

            $application = EmployerJobApplication::create([
                'employer_job_id' => $job->id,
                'candidate_id' => $candidate->id,
                'jd_mock_id' => $job->currentMock()?->id,
                'cv_document_id' => $document->id,
                'cv_match_pct' => $cvMatchPct,
                'mock_attempts' => MockInterview::query()
                    ->where('user_id', $candidate->id)
                    ->whereHas('blueprint', fn ($q) => $q->where('employer_job_id', $job->id))
                    ->count(),
                'stage' => EmployerApplicationStage::Applied->value,
                'knockout_answers' => $knockoutAnswers,
            ]);

            $application->transitions()->create([
                'from_stage' => null,
                'to_stage' => EmployerApplicationStage::Applied->value,
                'actor_type' => 'user',
                'actor_id' => $candidate->id,
                'occurred_at' => now(),
            ]);

            ApplicationReceived::dispatch($application);

            // The mock already happened — grade immediately rather than
            // waiting on AttachEmployerJobMockScore, which only fires on a
            // future MockCompleted event and would never see this one.
            return $this->grade->handle($application, $completedInterview);
        });
    }
}

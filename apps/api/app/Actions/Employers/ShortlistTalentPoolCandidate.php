<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerApplicationStage;
use App\Models\CvDocument;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\TalentPoolShortlist;
use App\Models\User;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;

/**
 * One click from the Talent Pool: put them on the pipeline board at
 * Shortlisted (so this shows up right where PRD-E F5 already expects a
 * shortlisted candidate to live — /employer/pipeline), record who did it
 * and when, and tell the candidate over WhatsApp. Idempotent per (job,
 * candidate) — a second click never sends a second message and never
 * pushes an already-further-along application backwards.
 */
final readonly class ShortlistTalentPoolCandidate
{
    public function __construct(private Messenger $messenger) {}

    public function handle(EmployerJob $job, User $candidate, User $shortlistedBy): TalentPoolShortlist
    {
        return app(TenantContext::class)->run($job->tenant, function () use ($job, $candidate, $shortlistedBy): TalentPoolShortlist {
            $existing = TalentPoolShortlist::query()
                ->where('employer_job_id', $job->id)
                ->where('candidate_id', $candidate->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $this->ensureOnPipeline($job, $candidate);

            $shortlist = TalentPoolShortlist::create([
                'tenant_id' => $job->tenant_id,
                'employer_job_id' => $job->id,
                'candidate_id' => $candidate->id,
                'shortlisted_by_user_id' => $shortlistedBy->id,
            ]);

            $this->messenger->send($candidate, 'talent_pool_shortlisted', [
                'name' => $candidate->name,
                'role' => $job->title,
                'company' => $job->workspace?->name ?? 'BrowseJobs',
            ]);

            return $shortlist;
        });
    }

    /**
     * A Talent Pool candidate hasn't necessarily applied here yet — no
     * job-specific mock, no tailored CV — so this doesn't reuse
     * ApplyToEmployerJob's strict flow. It creates a lighter application
     * row directly at Shortlisted (every field that flow requires but this
     * one can't supply, like jd_mock_id, is nullable), or — if they'd
     * already applied on their own — advances that real application to
     * Shortlisted, but only forward: an application already further along
     * (L1, Offer, …) is left exactly where it is.
     */
    private function ensureOnPipeline(EmployerJob $job, User $candidate): void
    {
        $application = EmployerJobApplication::query()
            ->where('employer_job_id', $job->id)
            ->where('candidate_id', $candidate->id)
            ->first();

        if ($application === null) {
            EmployerJobApplication::create([
                'tenant_id' => $job->tenant_id,
                'employer_job_id' => $job->id,
                'candidate_id' => $candidate->id,
                'cv_document_id' => CvDocument::query()->where('user_id', $candidate->id)->latest()->value('id'),
                'mock_attempts' => 0,
                'stage' => EmployerApplicationStage::Shortlisted->value,
            ]);

            return;
        }

        if ($application->stage->canAdvanceTo(EmployerApplicationStage::Shortlisted)) {
            $application->update(['stage' => EmployerApplicationStage::Shortlisted->value]);
        }
    }
}

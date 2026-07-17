<?php

declare(strict_types=1);

namespace App\Actions\Placement;

use App\Models\CvDocument;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use App\Support\Placement\PlacementPool;
use Illuminate\Validation\ValidationException;

/**
 * Student applies to a posting (PRD §6.11). Server-enforced pool gate —
 * eligibility is checked here, not just drawn on the checklist — plus one
 * application per posting and an approved CV attached at apply time (the
 * exact document the recruiter receives).
 */
final readonly class ApplyToJob
{
    public function __construct(private PlacementPool $pool) {}

    public function handle(User $student, int $postingId, ?int $cvDocumentId, bool $celebrateConsent, bool $celebrateAnonymous): JobApplication
    {
        $posting = JobPosting::query()
            ->where('status', JobPosting::STATUS_OPEN)
            ->findOrFail($postingId);

        $status = $this->pool->statusFor($student);
        if (! $status['eligible']) {
            throw ValidationException::withMessages([
                'pool' => 'Complete your placement checklist before applying — see what is pending on your Placement page.',
            ]);
        }

        $cv = CvDocument::query()
            ->where('user_id', $student->id)
            ->where('status', CvDocument::STATUS_APPROVED)
            ->when($cvDocumentId !== null, fn ($q) => $q->where('id', $cvDocumentId))
            ->latest('version')
            ->first();

        if ($cv === null) {
            throw ValidationException::withMessages(['cv_document_id' => 'Pick an approved CV version to apply with.']);
        }

        $exists = JobApplication::query()
            ->where('job_posting_id', $posting->id)
            ->where('user_id', $student->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['job_posting_id' => 'You have already applied to this posting.']);
        }

        return JobApplication::query()->create([
            'tenant_id' => $student->tenant_id,
            'job_posting_id' => $posting->id,
            'user_id' => $student->id,
            'cv_document_id' => $cv->id,
            'status' => JobApplication::STATUS_APPLIED,
            'celebrate_consent_at' => $celebrateConsent ? now() : null,
            'celebrate_anonymous' => $celebrateAnonymous,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\JobFeed;

use App\Actions\Cv\GenerateCv;
use App\Models\CvDocument;
use App\Models\JobApplication;
use App\Models\JobFeedItem;
use App\Models\JobFeedSave;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Apply Assist (PRD §6.22): applying to a feed item auto-tailors the student's CV
 * to THIS job's JD (free — applying is the outcome we want, so it's not taxed like
 * standalone §6.7 generations), logs the application in the tracker, and marks the
 * feed item applied. The deep link to the source posting is returned for the UI to
 * open. Idempotent per student+item.
 */
final readonly class ApplyToFeedItem
{
    public function __construct(private GenerateCv $cv) {}

    public function handle(User $student, JobFeedItem $item): JobApplication
    {
        return app(TenantContext::class)->run($student->tenant, function () use ($student, $item): JobApplication {
            $existing = JobApplication::query()
                ->where('user_id', $student->id)
                ->where('job_feed_item_id', $item->id)
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages(['job' => 'You have already applied to this role.']);
            }

            // Free JD-tailored CV for this application (never spends a CV credit).
            $document = $this->cv->handle($student, CvDocument::SOURCE_TAILORED, $item->description);

            $application = JobApplication::query()->create([
                'tenant_id' => $student->tenant_id,
                'job_feed_item_id' => $item->id,
                'user_id' => $student->id,
                'cv_document_id' => $document->id,
                'status' => JobApplication::STATUS_APPLIED,
            ]);

            JobFeedSave::query()->updateOrCreate(
                ['user_id' => $student->id, 'job_feed_item_id' => $item->id],
                ['tenant_id' => $student->tenant_id, 'state' => JobFeedSave::STATE_APPLIED],
            );

            return $application;
        });
    }
}

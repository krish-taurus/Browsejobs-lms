<?php

declare(strict_types=1);

namespace App\Actions\Placement;

use App\Actions\Reviews\SendReviewRequest;
use App\Enums\ReviewStage;
use App\Models\Celebration;
use App\Models\InAppNotification;
use App\Models\JobApplication;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Placement-officer stage moves (PRD §6.11). Marking PLACED is the big one:
 * timestamps the win, spawns the consent-gated celebration DRAFT (staff
 * publish it via the P3.7 flow — never auto-broadcast), congratulates the
 * student, and raises the pay-after-placement fee milestone as a human
 * task + audit entry (money stays human-approved, PRD §7).
 */
final readonly class UpdateApplicationStatus
{
    public function __construct(
        private AuditLogger $audit,
        private SendReviewRequest $sendReviewRequest,
    ) {}

    /**
     * @param  array{status: string, offer_ctc_paise?: int|null, offer_role?: string|null, note?: string|null}  $input
     */
    public function handle(User $actor, JobApplication $application, array $input): JobApplication
    {
        $status = $input['status'];

        if (! in_array($status, JobApplication::PIPELINE, true)) {
            throw ValidationException::withMessages(['status' => 'Unknown pipeline stage.']);
        }

        $wasPlaced = $application->status === JobApplication::STATUS_PLACED;

        $application->update([
            'status' => $status,
            'note' => $input['note'] ?? $application->note,
            'offer_ctc_paise' => $input['offer_ctc_paise'] ?? $application->offer_ctc_paise,
            'offer_role' => $input['offer_role'] ?? $application->offer_role,
            'offer_accepted_at' => in_array($status, [JobApplication::STATUS_OFFER, JobApplication::STATUS_PLACED], true)
                ? ($application->offer_accepted_at ?? now())
                : $application->offer_accepted_at,
            'placed_at' => $status === JobApplication::STATUS_PLACED
                ? ($application->placed_at ?? now())
                : $application->placed_at,
        ]);

        if ($status === JobApplication::STATUS_PLACED && ! $wasPlaced) {
            $this->onPlaced($actor, $application->refresh());
        }

        return $application->refresh();
    }

    private function onPlaced(User $actor, JobApplication $application): void
    {
        $application->loadMissing(['student', 'posting']);
        $student = $application->student;
        if ($student === null) {
            return;
        }

        $this->audit->log('placement.placed', $application, [
            'company' => $application->posting?->company,
            'role' => $application->offer_role ?? $application->posting?->title,
            'fee_milestone' => 'pay-after-placement instalment due — activate per policy',
        ], $actor);

        InAppNotification::query()->create([
            'tenant_id' => $application->tenant_id,
            'user_id' => $student->id,
            'title' => 'You did it — placed! 🎉',
            'body' => 'Congratulations from everyone here. Your placement team will be in touch about the details.',
            'url' => '/placement',
        ]);

        // Pay-after-placement milestone stays a HUMAN step: flag the officer.
        InAppNotification::query()->create([
            'tenant_id' => $application->tenant_id,
            'user_id' => $actor->id,
            'title' => "Fee milestone: {$student->name} placed",
            'body' => 'Activate the pay-after-placement instalment on their fee plan per policy.',
            'url' => '/admin/payments',
        ]);

        // Consent-first celebration DRAFT (PRD §6.18) — staff publish it.
        if ($application->celebrate_consent_at !== null) {
            Celebration::query()->create([
                'tenant_id' => $application->tenant_id,
                'student_id' => $student->id,
                'display_mode' => $application->celebrate_anonymous ? 'anonymous' : 'named',
                'anonymous_label' => $application->celebrate_anonymous ? 'A recent graduate' : null,
                'role_title' => $application->offer_role ?? (string) $application->posting?->title,
                'company' => (string) $application->posting?->company,
                'consented_at' => $application->celebrate_consent_at,
                'created_by' => $actor->id,
            ]);
        }

        // Final rung of the review ladder (Platform Spec §3): ask the newly-placed
        // student for a review. Idempotent per student+stage.
        $this->sendReviewRequest->handle($student, ReviewStage::Placement);
    }
}

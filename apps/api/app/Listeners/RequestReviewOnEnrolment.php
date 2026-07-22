<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Reviews\SendReviewRequest;
use App\Enums\ReviewStage;
use App\Events\PaymentCaptured;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Requests a review once the candidate has paid for the course (Platform Spec §3
 * review ladder, stage 3 — "after paying + counselling"). Fires only on the
 * registration instalment (seq 1) so later EMIs don't re-prompt; SendReviewRequest
 * is idempotent per student+stage as a second guard.
 */
final class RequestReviewOnEnrolment implements ShouldQueue
{
    public function __construct(private readonly SendReviewRequest $sendReviewRequest) {}

    public function handle(PaymentCaptured $event): void
    {
        if ((int) $event->instalment->seq !== 1) {
            return;
        }

        $feePlan = $event->payment->feePlan;
        $tenant = $feePlan?->tenant;
        if ($feePlan === null || $tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($feePlan): void {
            $student = $feePlan->student;
            if ($student === null) {
                return;
            }

            $this->sendReviewRequest->handle($student, ReviewStage::Enrolment, $feePlan->batch);
        });
    }
}

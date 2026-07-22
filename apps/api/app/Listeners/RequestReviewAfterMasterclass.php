<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Reviews\SendReviewRequest;
use App\Enums\BatchMemberStatus;
use App\Enums\ReviewStage;
use App\Events\MasterclassCompleted;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Requests a review from every masterclass attendee once the masterclass is
 * marked complete (Platform Spec §3 review ladder, stage 1). Sends a one-tap
 * magic link to the review page. Idempotent per student+stage via SendReviewRequest.
 */
final class RequestReviewAfterMasterclass implements ShouldQueue
{
    public function __construct(private readonly SendReviewRequest $sendReviewRequest) {}

    public function handle(MasterclassCompleted $event): void
    {
        $masterclass = $event->masterclass;
        $tenant = $masterclass->tenant;
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($masterclass): void {
            $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
            $members = $masterclass->members()->whereIn('status', $occupying)->with('student')->get();

            foreach ($members as $member) {
                $student = $member->student;
                if ($student === null) {
                    continue;
                }

                $this->sendReviewRequest->handle($student, ReviewStage::Masterclass, $masterclass);
            }
        });
    }
}

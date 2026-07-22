<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Reviews\SendReviewRequest;
use App\Enums\BatchMemberStatus;
use App\Enums\ReviewStage;
use App\Events\BootcampCompleted;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Auto-requests a review from every bootcamp attendee when the bootcamp
 * completes (Platform Spec §3 review ladder, stage 2). Sends a one-tap magic
 * link to the review page. Idempotent per student+stage via SendReviewRequest.
 */
final class RequestTestimonials implements ShouldQueue
{
    public function __construct(private readonly SendReviewRequest $sendReviewRequest) {}

    public function handle(BootcampCompleted $event): void
    {
        $bootcamp = $event->bootcamp;
        $tenant = $bootcamp->tenant;
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($bootcamp): void {
            $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
            $members = $bootcamp->members()->whereIn('status', $occupying)->with('student')->get();

            foreach ($members as $member) {
                $student = $member->student;
                if ($student === null) {
                    continue;
                }

                $this->sendReviewRequest->handle($student, ReviewStage::Bootcamp, $bootcamp);
            }
        });
    }
}

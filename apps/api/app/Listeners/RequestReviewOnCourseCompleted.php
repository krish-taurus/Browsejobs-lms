<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Reviews\SendReviewRequest;
use App\Enums\ReviewStage;
use App\Events\CourseCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Requests a review when a student finishes their course (Platform Spec §3
 * review ladder, stage 4). Idempotent per student+stage via SendReviewRequest.
 */
final class RequestReviewOnCourseCompleted implements ShouldQueue
{
    public function __construct(private readonly SendReviewRequest $sendReviewRequest) {}

    public function handle(CourseCompleted $event): void
    {
        $this->sendReviewRequest->handle(
            $event->user,
            ReviewStage::CourseComplete,
            null,
            $event->course->name,
        );
    }
}

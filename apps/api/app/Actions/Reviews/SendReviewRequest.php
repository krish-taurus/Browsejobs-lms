<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\ReviewStage;
use App\Enums\TestimonialStatus;
use App\Models\Batch;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;

/**
 * Sends a candidate a one-tap review request for a given funnel stage (Platform
 * Spec §3 review ladder). The magic link lands them on the review page tagged
 * with the stage; there they rate, and 4★+ ratings are handed off to Google.
 * Idempotent per student+stage — never double-prompts the same stage while a
 * review for it is already pending or has already been given. Pass `$force` to
 * bypass that guard for a deliberate manual/admin re-send.
 */
final readonly class SendReviewRequest
{
    public function __construct(private Messenger $messenger) {}

    public function handle(User $student, ReviewStage $stage, ?Batch $batch = null, ?string $courseName = null, bool $force = false): bool
    {
        if (! config('vouchers.review_request.enabled', true)) {
            return false;
        }

        return app(TenantContext::class)->run($student->tenant, function () use ($student, $stage, $batch, $courseName, $force): bool {
            // Don't re-prompt a stage the student has already engaged with — a
            // pending review, or one already resolved (approved/rejected/private).
            // A forced (manual) send skips this guard.
            if (! $force) {
                $already = Testimonial::query()
                    ->where('user_id', $student->id)
                    ->where('stage', $stage->value)
                    ->whereIn('status', [
                        TestimonialStatus::Pending->value,
                        TestimonialStatus::Approved->value,
                        TestimonialStatus::PrivateFeedback->value,
                    ])
                    ->exists();
                if ($already) {
                    return false;
                }
            }

            $base = rtrim((string) config('app.frontend_url', ''), '/');
            $query = http_build_query(array_filter([
                'stage' => $stage->value,
                'batch' => $batch?->id,
            ]));
            $redirect = "{$base}/testimonial".($query !== '' ? "?{$query}" : '');

            $this->messenger->send($student, 'review_request', [
                'name' => $student->name,
                'course' => $courseName ?? $batch?->course?->name ?? '',
            ], [
                'magic' => [
                    'action' => 'review.submit',
                    'payload' => array_filter([
                        'batch_id' => $batch?->id,
                        'stage' => $stage->value,
                        'redirect' => $redirect,
                    ], fn ($v) => $v !== null),
                ],
            ]);

            return true;
        });
    }
}

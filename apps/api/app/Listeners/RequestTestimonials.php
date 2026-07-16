<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\BatchMemberStatus;
use App\Events\BootcampCompleted;
use App\Models\Testimonial;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Auto-requests a testimonial from every bootcamp attendee when the bootcamp
 * completes (PRD §5 Stage 3). Sends a one-tap magic link to the testimonial form.
 * Idempotent — skips a student who already has a testimonial for this batch, so a
 * re-completed bootcamp does not double-send.
 */
final class RequestTestimonials implements ShouldQueue
{
    public function __construct(private readonly Messenger $messenger) {}

    public function handle(BootcampCompleted $event): void
    {
        if (! config('vouchers.review_request.enabled', true)) {
            return;
        }

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

                $already = Testimonial::query()
                    ->where('user_id', $student->id)
                    ->where('batch_id', $bootcamp->id)
                    ->exists();
                if ($already) {
                    continue;
                }

                $redirect = rtrim((string) config('app.frontend_url', ''), '/')."/testimonial?batch={$bootcamp->id}";

                $this->messenger->send($student, 'review_request', [
                    'name' => $student->name,
                    'course' => $bootcamp->course?->name ?? '',
                ], [
                    'magic' => [
                        'action' => 'review.submit',
                        'payload' => ['batch_id' => $bootcamp->id, 'redirect' => $redirect],
                    ],
                ]);
            }
        });
    }
}

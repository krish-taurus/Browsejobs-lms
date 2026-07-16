<?php

declare(strict_types=1);

namespace App\Actions\Testimonials;

use App\Actions\Vouchers\IssueVoucher;
use App\Enums\TestimonialStatus;
use App\Models\Review;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherIssue;
use App\Support\Audit\AuditLogger;
use App\Support\Messaging\Messenger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Verifies a testimonial (PRD §5 Stage 3 "verified submission"). Publishes it to
 * the /reviews wall (writes a `platform` review), issues the tenant's review
 * reward voucher, and messages the student the code. Idempotent — re-approving an
 * approved testimonial is a no-op. The voucher is tied to this platform
 * testimonial, never a Google review.
 */
final readonly class ApproveTestimonial
{
    public function __construct(
        private IssueVoucher $issueVoucher,
        private Messenger $messenger,
        private AuditLogger $audit,
    ) {}

    public function handle(Testimonial $testimonial, ?User $actor = null): Testimonial
    {
        return app(TenantContext::class)->run($testimonial->tenant, function () use ($testimonial, $actor): Testimonial {
            if ($testimonial->status === TestimonialStatus::Approved) {
                return $testimonial;
            }

            if (! $testimonial->consent_publish) {
                throw ValidationException::withMessages(['consent' => 'Cannot publish a testimonial without the student’s consent.']);
            }

            $student = $testimonial->student;

            $review = Review::query()->create([
                'tenant_id' => $testimonial->tenant_id,
                'author_name' => $student->name,
                'course_slug' => $testimonial->course_slug,
                'rating' => $testimonial->rating,
                'body' => $testimonial->body,
                'source' => 'platform',
                'reviewed_on' => now()->toDateString(),
                'is_active' => true,
                'sort_order' => 0,
            ]);

            $issue = $this->issueReward($testimonial, $actor);

            $testimonial->update([
                'status' => TestimonialStatus::Approved->value,
                'review_id' => $review->id,
                'voucher_issue_id' => $issue?->id,
                'reviewed_by' => $actor?->id,
                'reviewed_at' => now(),
            ]);

            $this->audit->log(
                action: 'testimonial.approved',
                target: $testimonial,
                metadata: ['review_id' => $review->id, 'voucher_issue_id' => $issue?->id],
                actor: $actor,
            );

            if ($issue !== null) {
                $this->messenger->send($student, 'voucher_issued', [
                    'name' => $student->name,
                    'code' => $issue->code,
                    'expires' => $issue->expires_at?->toDateString() ?? '',
                ]);
            }

            return $testimonial->refresh();
        });
    }

    private function issueReward(Testimonial $testimonial, ?User $actor): ?VoucherIssue
    {
        $voucher = Voucher::query()->reviewReward()->orderBy('id')->first();

        if ($voucher === null) {
            return null;
        }

        return $this->issueVoucher->handle($testimonial->student, $voucher, $testimonial, $actor);
    }
}

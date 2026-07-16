<?php

declare(strict_types=1);

namespace App\Actions\Testimonials;

use App\Enums\TestimonialStatus;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;

/**
 * Rejects a testimonial during moderation (PRD §5 Stage 3). Nothing is published
 * and no voucher is issued.
 */
final readonly class RejectTestimonial
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Testimonial $testimonial, string $reason, ?User $actor = null): Testimonial
    {
        return app(TenantContext::class)->run($testimonial->tenant, function () use ($testimonial, $reason, $actor): Testimonial {
            $testimonial->update([
                'status' => TestimonialStatus::Rejected->value,
                'reviewed_by' => $actor?->id,
                'reviewed_at' => now(),
                'reject_reason' => $reason,
            ]);

            $this->audit->log(
                action: 'testimonial.rejected',
                target: $testimonial,
                metadata: ['reason' => $reason],
                actor: $actor,
            );

            return $testimonial;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Vouchers;

use App\Enums\VoucherIssueStatus;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherIssue;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issues one voucher to a student (PRD §5 Stage 3). Enforces the voucher's usage
 * and per-user limits, mints a unique code, and stamps an expiry window.
 * Idempotent per testimonial — a testimonial that already carries an issue
 * returns it unchanged.
 */
final readonly class IssueVoucher
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $student, Voucher $voucher, ?Testimonial $testimonial = null, ?User $actor = null): VoucherIssue
    {
        return app(TenantContext::class)->run($student->tenant, function () use ($student, $voucher, $testimonial, $actor): VoucherIssue {
            if ($testimonial !== null && $testimonial->voucher_issue_id !== null) {
                return VoucherIssue::query()->findOrFail($testimonial->voucher_issue_id);
            }

            $this->enforceLimits($student, $voucher);

            $issue = VoucherIssue::query()->create([
                'tenant_id' => $student->tenant_id,
                'voucher_id' => $voucher->id,
                'user_id' => $student->id,
                'testimonial_id' => $testimonial?->id,
                'code' => $this->uniqueCode(),
                'status' => VoucherIssueStatus::Issued->value,
                'issued_at' => now(),
                'expires_at' => $this->expiryFor($voucher),
            ]);

            $this->audit->log(
                action: 'voucher.issued',
                target: $issue,
                metadata: ['voucher_id' => $voucher->id, 'code' => $issue->code, 'testimonial_id' => $testimonial?->id],
                actor: $actor,
            );

            return $issue;
        });
    }

    private function enforceLimits(User $student, Voucher $voucher): void
    {
        $perUser = VoucherIssue::query()
            ->where('voucher_id', $voucher->id)
            ->where('user_id', $student->id)
            ->where('status', '!=', VoucherIssueStatus::Void->value)
            ->count();

        if ($perUser >= $voucher->per_user_limit) {
            throw ValidationException::withMessages(['voucher' => 'This student has already received this voucher.']);
        }

        if ($voucher->usage_limit !== null) {
            $total = VoucherIssue::query()
                ->where('voucher_id', $voucher->id)
                ->where('status', '!=', VoucherIssueStatus::Void->value)
                ->count();

            if ($total >= $voucher->usage_limit) {
                throw ValidationException::withMessages(['voucher' => 'This voucher has reached its usage limit.']);
            }
        }
    }

    private function expiryFor(Voucher $voucher): Carbon
    {
        if ($voucher->valid_until !== null) {
            return $voucher->valid_until->copy()->endOfDay();
        }

        $days = $voucher->valid_days ?? (int) config('vouchers.default_valid_days', 30);

        return now()->addDays($days);
    }

    private function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (VoucherIssue::query()->withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }
}

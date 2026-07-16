<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VoucherIssueStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A voucher issued to one student (PRD §5 Stage 3). One row per issuance — the
 * unit of redemption analytics + usage-limit enforcement. When applied it feeds
 * `fee_plan.discount_paise`.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $voucher_id
 * @property int $user_id
 * @property int|null $testimonial_id
 * @property string $code
 * @property VoucherIssueStatus $status
 * @property int|null $discount_paise
 * @property int|null $fee_plan_id
 * @property Carbon $issued_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $applied_at
 * @property-read Voucher $voucher
 */
class VoucherIssue extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'voucher_id', 'user_id', 'testimonial_id', 'code', 'status',
        'discount_paise', 'fee_plan_id', 'issued_at', 'expires_at', 'applied_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VoucherIssueStatus::class,
            'discount_paise' => 'integer',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /** Still spendable: issued and not past its expiry window. */
    public function isRedeemable(): bool
    {
        return $this->status === VoucherIssueStatus::Issued
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Testimonial, $this>
     */
    public function testimonial(): BelongsTo
    {
        return $this->belongsTo(Testimonial::class);
    }

    /**
     * @return BelongsTo<FeePlan, $this>
     */
    public function feePlan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class);
    }
}

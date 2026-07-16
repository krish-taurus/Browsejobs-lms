<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VoucherType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An admin-configured voucher definition (PRD §6.8). `value` is paise for a flat
 * voucher, basis points for a percent voucher. The `is_review_reward` voucher is
 * auto-issued on testimonial approval. Money math is integer paise.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string|null $code
 * @property string $name
 * @property VoucherType $type
 * @property int $value
 * @property int|null $max_discount_paise
 * @property int|null $valid_days
 * @property Carbon|null $valid_until
 * @property int|null $course_id
 * @property int|null $usage_limit
 * @property int $per_user_limit
 * @property bool $allow_stacking
 * @property bool $is_review_reward
 * @property bool $active
 */
class Voucher extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'code', 'name', 'type', 'value', 'max_discount_paise',
        'valid_days', 'valid_until', 'course_id', 'usage_limit', 'per_user_limit',
        'allow_stacking', 'is_review_reward', 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VoucherType::class,
            'value' => 'integer',
            'max_discount_paise' => 'integer',
            'valid_days' => 'integer',
            'valid_until' => 'date',
            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'allow_stacking' => 'boolean',
            'is_review_reward' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * The discount (paise) this voucher yields against a total (paise). Percent
     * vouchers round down, are capped at `max_discount_paise`, and never exceed
     * the total.
     */
    public function computeDiscount(int $totalPaise): int
    {
        $discount = $this->type === VoucherType::Flat
            ? $this->value
            : intdiv($totalPaise * $this->value, 10_000);

        if ($this->max_discount_paise !== null) {
            $discount = min($discount, $this->max_discount_paise);
        }

        return max(0, min($discount, $totalPaise));
    }

    /**
     * @param  Builder<Voucher>  $query
     */
    public function scopeReviewReward(Builder $query): void
    {
        $query->where('is_review_reward', true)->where('active', true);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<VoucherIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(VoucherIssue::class);
    }
}

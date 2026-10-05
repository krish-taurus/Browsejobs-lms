<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One purchased Career Boost (PRD-E, Aug 2026): a running counter of the
 * bonus mock attempts and job applications a candidate bought, plus the
 * wider-market view cap while it's active. See ActiveCareerBoost for how
 * these are read and consumed — this model is deliberately just storage.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property int $product_purchase_id
 * @property int $mock_bonus_total
 * @property int $mock_bonus_used
 * @property int $job_application_bonus_total
 * @property int $job_application_bonus_used
 * @property int $wider_market_job_limit
 * @property \Illuminate\Support\Carbon $expires_at
 */
class CareerBoost extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'product_purchase_id',
        'mock_bonus_total', 'mock_bonus_used',
        'job_application_bonus_total', 'job_application_bonus_used',
        'wider_market_job_limit', 'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mock_bonus_total' => 'integer',
            'mock_bonus_used' => 'integer',
            'job_application_bonus_total' => 'integer',
            'job_application_bonus_used' => 'integer',
            'wider_market_job_limit' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ProductPurchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ProductPurchase::class, 'product_purchase_id');
    }
}

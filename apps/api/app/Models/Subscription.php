<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Razorpay subscription backing Career+ (PRD §6.17).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int|null $product_id
 * @property string|null $razorpay_subscription_id
 * @property SubscriptionStatus $status
 * @property Carbon|null $current_period_end
 * @property Carbon|null $cancelled_at
 */
class Subscription extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'product_id', 'razorpay_subscription_id',
        'status', 'current_period_end', 'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

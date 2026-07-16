<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Razorpay payment attempt against an instalment (PRD §6.8).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $fee_plan_id
 * @property int $instalment_id
 * @property string|null $razorpay_order_id
 * @property string|null $razorpay_payment_id
 * @property int $amount_paise
 * @property PaymentStatus $status
 * @property string|null $method
 * @property Carbon|null $captured_at
 * @property array<string, mixed>|null $raw
 */
class Payment extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'fee_plan_id', 'instalment_id', 'razorpay_order_id', 'razorpay_payment_id',
        'amount_paise', 'status', 'method', 'captured_at', 'raw',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'status' => PaymentStatus::class,
            'captured_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FeePlan, $this>
     */
    public function feePlan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class);
    }

    /**
     * @return BelongsTo<Instalment, $this>
     */
    public function instalment(): BelongsTo
    {
        return $this->belongsTo(Instalment::class);
    }
}

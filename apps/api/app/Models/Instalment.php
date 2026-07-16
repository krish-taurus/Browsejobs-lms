<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InstalmentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One scheduled instalment of a fee plan (PRD §6.8).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $fee_plan_id
 * @property int $seq
 * @property int $amount_paise
 * @property Carbon $due_on
 * @property InstalmentStatus $status
 * @property Carbon|null $paid_at
 * @property string|null $razorpay_order_id
 * @property string|null $razorpay_payment_link_id
 * @property string|null $payment_link_url
 */
class Instalment extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'fee_plan_id', 'seq', 'amount_paise', 'due_on', 'status',
        'paid_at', 'razorpay_order_id', 'razorpay_payment_link_id', 'payment_link_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'amount_paise' => 'integer',
            'due_on' => 'date',
            'status' => InstalmentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FeePlan, $this>
     */
    public function feePlan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class);
    }
}

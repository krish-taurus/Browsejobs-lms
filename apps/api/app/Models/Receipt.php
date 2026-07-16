<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A branded GST tax invoice for a captured payment (PRD §6.8). Inclusive GST:
 * total_paise = taxable_paise + cgst_paise + sgst_paise.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $payment_id
 * @property int $fee_plan_id
 * @property string $number
 * @property int $taxable_paise
 * @property int $cgst_paise
 * @property int $sgst_paise
 * @property int $total_paise
 * @property string|null $storage_path
 * @property string $status
 */
class Receipt extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RENDERED = 'rendered';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'payment_id', 'fee_plan_id', 'number',
        'taxable_paise', 'cgst_paise', 'sgst_paise', 'total_paise',
        'storage_path', 'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taxable_paise' => 'integer',
            'cgst_paise' => 'integer',
            'sgst_paise' => 'integer',
            'total_paise' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<FeePlan, $this>
     */
    public function feePlan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeePlanStatus;
use App\Enums\FeePlanType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's fee plan for a paid batch (PRD §6.8). Money in paise integers.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int $batch_id
 * @property FeePlanType $type
 * @property int $total_paise
 * @property int $discount_paise
 * @property int $gst_rate_bps
 * @property string $currency
 * @property FeePlanStatus $status
 * @property int|null $created_by
 */
class FeePlan extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'batch_id', 'type', 'total_paise', 'discount_paise',
        'gst_rate_bps', 'currency', 'status', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FeePlanType::class,
            'status' => FeePlanStatus::class,
            'total_paise' => 'integer',
            'discount_paise' => 'integer',
            'gst_rate_bps' => 'integer',
        ];
    }

    /** Net amount payable after any discount (paise). */
    public function netPayablePaise(): int
    {
        return max(0, $this->total_paise - $this->discount_paise);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return HasMany<Instalment, $this>
     */
    public function instalments(): HasMany
    {
        return $this->hasMany(Instalment::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}

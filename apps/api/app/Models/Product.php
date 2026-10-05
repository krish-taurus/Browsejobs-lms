<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductKind;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sellable product (PRD §6.17): credit pack, Career+ subscription, self-paced
 * course, or Career Boost bundle. Money in paise.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $sku
 * @property string $name
 * @property string|null $feature
 * @property ProductKind $kind
 * @property int $price_paise
 * @property int $grant_amount
 * @property int|null $period_days
 * @property int|null $source_batch_id
 * @property int|null $mock_bonus_amount
 * @property int|null $job_application_bonus_amount
 * @property int|null $wider_market_job_limit
 * @property bool $active
 */
class Product extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'sku', 'name', 'feature', 'kind', 'price_paise',
        'grant_amount', 'period_days', 'source_batch_id', 'active',
        'mock_bonus_amount', 'job_application_bonus_amount', 'wider_market_job_limit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProductKind::class,
            'price_paise' => 'integer',
            'grant_amount' => 'integer',
            'period_days' => 'integer',
            'active' => 'boolean',
            'mock_bonus_amount' => 'integer',
            'job_application_bonus_amount' => 'integer',
            'wider_market_job_limit' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'source_batch_id');
    }
}

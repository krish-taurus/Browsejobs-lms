<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One column of the CRM pipeline funnel (PRD §6.12). Ordered per tenant by
 * `position`; `is_won`/`is_lost` mark terminal outcomes for reporting.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $name
 * @property string $slug
 * @property int $position
 * @property bool $is_won
 * @property bool $is_lost
 */
class LeadStage extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'name', 'slug', 'position', 'is_won', 'is_lost',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}

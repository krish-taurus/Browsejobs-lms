<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One tenant's Market Pulse digest for one day (PRD §6.19) — AI-summarised
 * from the curated items, with a deterministic fallback.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property Carbon $digest_date
 * @property string $narrative
 * @property list<int> $item_ids
 * @property string $source
 */
class PulseDigest extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'digest_date', 'narrative', 'item_ids', 'source', 'ai_event_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'digest_date' => 'date',
            'item_ids' => 'array',
        ];
    }
}

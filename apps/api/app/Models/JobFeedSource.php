<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A configured job-feed source (PRD §6.22). Its `kind` selects the adapter that
 * knows how to pull from it; `config` carries adapter-specific settings.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $name
 * @property string $kind
 * @property array<string, mixed>|null $config
 * @property int $priority
 * @property bool $is_active
 * @property Carbon|null $last_synced_at
 */
class JobFeedSource extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const KIND_INTERNAL = 'internal';

    public const KIND_PARTNER = 'partner';

    public const KIND_API = 'api';

    public const KIND_ATS = 'ats';

    public const KIND_CSV = 'csv';

    public const KIND_SCRAPER = 'scraper';

    public const KINDS = [self::KIND_INTERNAL, self::KIND_PARTNER, self::KIND_API, self::KIND_ATS, self::KIND_CSV, self::KIND_SCRAPER];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'name', 'kind', 'config', 'priority', 'is_active', 'last_synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<JobFeedItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(JobFeedItem::class);
    }
}

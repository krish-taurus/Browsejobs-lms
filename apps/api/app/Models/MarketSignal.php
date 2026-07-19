<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A dated market-intelligence snapshot (platform-global — derived from public
 * news and job-feed aggregates, never tenant data; ADR precedent: 0039).
 *
 * @property int $id
 * @property string $kind
 * @property array $payload
 * @property Carbon $effective_on
 * @property string $source
 */
final class MarketSignal extends Model
{
    protected $fillable = ['kind', 'payload', 'effective_on', 'source'];

    public const KIND_CITY_PULSE = 'city_pulse';

    public const KIND_FUNDING = 'funding';

    public const KIND_OUTLOOK = 'outlook';

    public const KIND_DOMAIN_RANK = 'domain_rank';

    public const KIND_FUNDING_RADAR = 'funding_radar';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'effective_on' => 'date',
        ];
    }

    /** Latest snapshot for a kind, if any. */
    public static function latest_(string $kind): ?self
    {
        return self::query()->where('kind', $kind)->orderByDesc('effective_on')->first();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A curated, sourced funding/expansion news item feeding the Funding Radar
 * (platform-global — public news, never tenant data). Company names are only
 * ever shown alongside their public source.
 *
 * @property int $id
 * @property string $kind
 * @property string|null $headline
 * @property string|null $company
 * @property string $sector
 * @property string $round
 * @property string $hub
 * @property int $hiring_lag_months
 * @property array $roles
 * @property array $skills
 * @property string $source_name
 * @property string|null $source_url
 * @property Carbon $published_on
 * @property bool $active
 */
final class FundingNews extends Model
{
    protected $fillable = [
        'kind', 'headline', 'company', 'sector', 'round', 'hub', 'hiring_lag_months',
        'roles', 'skills', 'source_name', 'source_url', 'published_on', 'active',
    ];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'skills' => 'array',
            'published_on' => 'date',
            'active' => 'boolean',
        ];
    }
}

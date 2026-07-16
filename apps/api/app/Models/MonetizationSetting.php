<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-tenant freemium config (PRD §6.17). One row per tenant.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $cv_free_grants
 * @property int $voice_included_live
 * @property int $voice_included_self_paced
 * @property int $self_paced_pct_bps
 * @property bool $text_practice_enabled
 */
class MonetizationSetting extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'cv_free_grants', 'voice_included_live', 'voice_included_self_paced',
        'self_paced_pct_bps', 'text_practice_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cv_free_grants' => 'integer',
            'voice_included_live' => 'integer',
            'voice_included_self_paced' => 'integer',
            'self_paced_pct_bps' => 'integer',
            'text_practice_enabled' => 'boolean',
        ];
    }
}

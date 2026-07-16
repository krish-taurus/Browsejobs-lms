<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A daily snapshot of a student's scores (PRD §6.4) — trajectory + daily movers.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property Carbon $captured_on
 * @property int $pri
 * @property int $engagement
 * @property int $risk_dropout
 * @property int $risk_placement
 */
class ScoreSnapshot extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'captured_on', 'pri', 'engagement', 'risk_dropout', 'risk_placement',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_on' => 'date',
            'pri' => 'integer',
            'engagement' => 'integer',
            'risk_dropout' => 'integer',
            'risk_placement' => 'integer',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One PRI weight calibration for a tenant (PRD §6.21), derived from placement
 * outcome correlations. Weights sum to 1 over mastery/engagement/attendance.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property array{mastery: float, engagement: float, attendance: float} $weights
 * @property array<string, float> $correlations
 * @property int $cohort_size
 * @property bool $applied
 * @property Carbon $computed_at
 */
class PriCalibration extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'weights', 'correlations', 'cohort_size', 'applied', 'computed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weights' => 'array',
            'correlations' => 'array',
            'cohort_size' => 'integer',
            'applied' => 'boolean',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * The active (latest applied) calibration weights for the current tenant, or
     * null when none — the ScoreCalculator then uses config defaults.
     *
     * @return array{mastery: float, engagement: float, attendance: float}|null
     */
    public static function activeWeights(): ?array
    {
        $row = self::query()
            ->where('applied', true)
            ->orderByDesc('computed_at')
            ->first();

        return $row?->weights;
    }
}

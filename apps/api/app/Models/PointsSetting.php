<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-tenant leaderboard weights (PRD §6.16 — "weights admin-configurable").
 * One row per tenant, created on demand with config defaults.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $attendance_points
 * @property int $punctuality_points
 * @property int $quiz_points
 * @property int $lab_points
 * @property int $streak_day_points
 * @property int $mock_points
 * @property int $daily_cap
 * @property int $attendance_min_pct
 */
class PointsSetting extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'attendance_points', 'punctuality_points', 'quiz_points',
        'lab_points', 'streak_day_points', 'mock_points', 'daily_cap', 'attendance_min_pct',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_points' => 'integer',
            'punctuality_points' => 'integer',
            'quiz_points' => 'integer',
            'lab_points' => 'integer',
            'streak_day_points' => 'integer',
            'mock_points' => 'integer',
            'daily_cap' => 'integer',
            'attendance_min_pct' => 'integer',
        ];
    }
}

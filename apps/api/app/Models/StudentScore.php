<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student's latest rule-based scores (PRD §6.4).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int $engagement
 * @property int $risk_dropout
 * @property int $risk_placement
 * @property int $pri
 * @property int $streak_days
 * @property array<int, array{module_id: int, name: string, pct: int, band: string}>|null $mastery
 * @property array{key: string, title: string, detail: string, href: ?string}|null $next_action
 * @property Carbon|null $computed_at
 */
class StudentScore extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'engagement', 'risk_dropout', 'risk_placement', 'pri',
        'streak_days', 'mastery', 'next_action', 'red_flags', 'computed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'engagement' => 'integer',
            'risk_dropout' => 'integer',
            'risk_placement' => 'integer',
            'pri' => 'integer',
            'streak_days' => 'integer',
            'mastery' => 'array',
            'next_action' => 'array',
            'red_flags' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

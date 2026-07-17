<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Week-1 white-glove onboarding (PRD §6.20): the guaranteed welcome call,
 * platform tour, and first-week check-in — tracked per student.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property Carbon|null $welcome_call_at
 * @property Carbon|null $platform_tour_at
 * @property Carbon|null $week1_checkin_at
 * @property int|null $assigned_to
 */
class OnboardingChecklist extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'welcome_call_at', 'platform_tour_at', 'week1_checkin_at', 'assigned_to',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'welcome_call_at' => 'datetime',
            'platform_tour_at' => 'datetime',
            'week1_checkin_at' => 'datetime',
        ];
    }
}

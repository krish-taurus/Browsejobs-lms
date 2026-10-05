<?php

declare(strict_types=1);

namespace App\Support\Entitlements;

use App\Models\CareerBoost;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reads and spends a candidate's Career Boost bonuses (PRD-E, Aug 2026) —
 * the purchasable top-up on the free-tier interview/application caps built
 * for Jobs for You. A candidate can hold more than one unexpired boost at
 * once (buying a second before the first runs out); their bonuses simply
 * add up, spent from whichever row expires soonest first so nothing is
 * wasted to an expiry while a later-bought row still has room.
 *
 * The free-tier caps themselves (MonetizationSetting) are untouched by any
 * of this — a boost is checked only after the free cap is already spent.
 */
final class ActiveCareerBoost
{
    private function activeFor(User $user): Builder
    {
        return CareerBoost::query()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now());
    }

    public function remainingMocks(User $user): int
    {
        return (int) $this->activeFor($user)->sum(DB::raw('mock_bonus_total - mock_bonus_used'));
    }

    public function remainingApplications(User $user): int
    {
        return (int) $this->activeFor($user)->sum(DB::raw('job_application_bonus_total - job_application_bonus_used'));
    }

    /** The highest wider-market cap among the candidate's active boosts, or 0 with none. */
    public function widerMarketLimit(User $user): int
    {
        return (int) ($this->activeFor($user)->max('wider_market_job_limit') ?? 0);
    }

    /** Spends one bonus mock attempt. False if there is none left to spend. */
    public function consumeMock(User $user): bool
    {
        return $this->consume($user, 'mock_bonus_total', 'mock_bonus_used');
    }

    /** Spends one bonus job application. False if there is none left to spend. */
    public function consumeApplication(User $user): bool
    {
        return $this->consume($user, 'job_application_bonus_total', 'job_application_bonus_used');
    }

    private function consume(User $user, string $totalColumn, string $usedColumn): bool
    {
        return DB::transaction(function () use ($user, $totalColumn, $usedColumn): bool {
            $row = $this->activeFor($user)
                ->whereColumn($usedColumn, '<', $totalColumn)
                ->orderBy('expires_at')
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return false;
            }

            $row->increment($usedColumn);

            return true;
        });
    }

    /** Whether the candidate has any unexpired boost at all — for status display. */
    public function active(User $user): bool
    {
        return $this->activeFor($user)->exists();
    }

    public function expiresAt(User $user): ?Carbon
    {
        return $this->activeFor($user)->orderByDesc('expires_at')->value('expires_at');
    }
}

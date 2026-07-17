<?php

declare(strict_types=1);

namespace App\Support\Points;

use App\Enums\BatchMemberStatus;
use App\Enums\PointsSource;
use App\Models\BatchMember;
use App\Models\InAppNotification;
use App\Models\PointsEvent;
use App\Models\PointsSetting;
use App\Models\StudentBadge;
use App\Models\User;
use App\Support\Scoring\ScoreCalculator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The points engine (PRD §6.16). Called ONLY from verified server-side flows
 * (attendance webhook, quiz grading, lab judging, telemetry) — that, plus the
 * per-source idempotency key and the per-day cap, is the anti-gaming design.
 *
 * Runs in webhook/queue contexts where no tenant context is bound, so every
 * query pins tenant_id explicitly and bypasses the global scope.
 */
final readonly class PointsService
{
    public function __construct(private ScoreCalculator $scores) {}

    public function settingsFor(int $tenantId): PointsSetting
    {
        return PointsSetting::query()->withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId],
            (array) config('points.defaults'),
        );
    }

    /**
     * Idempotently award points. Returns the created event, or null when the
     * award was a duplicate, zero-valued, or over the daily cap.
     */
    public function award(
        User $student,
        PointsSource $source,
        string $sourceKey,
        int $points,
        int $tenantId,
        ?int $batchId = null,
    ): ?PointsEvent {
        if ($points <= 0) {
            return null;
        }

        $existing = PointsEvent::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->where('source', $source->value)
            ->where('source_key', $sourceKey)
            ->exists();

        if ($existing) {
            return null;
        }

        // Daily cap (anti-gaming): clamp today's award to the remaining budget.
        $cap = $this->settingsFor($tenantId)->daily_cap;
        $todayTotal = (int) PointsEvent::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->whereDate('awarded_on', now()->toDateString())
            ->sum('points');

        $remaining = max(0, $cap - $todayTotal);
        if ($remaining === 0) {
            return null;
        }

        try {
            return PointsEvent::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'user_id' => $student->id,
                'batch_id' => $batchId ?? $this->activeBatchId($student),
                'source' => $source->value,
                'source_key' => $sourceKey,
                'points' => min($points, $remaining),
                'awarded_on' => now()->toDateString(),
            ]);
        } catch (QueryException) {
            // Lost a race on the unique key — the award already exists.
            return null;
        }
    }

    /**
     * Called after every telemetry write: award today's streak point once the
     * streak is >= 2 consecutive days, and the 7-Day Streak badge at 7.
     */
    public function evaluateStreak(User $student, int $tenantId): void
    {
        $today = now()->toDateString();

        $alreadyToday = PointsEvent::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->where('source', PointsSource::Streak->value)
            ->where('source_key', "day:{$today}")
            ->exists();

        if ($alreadyToday) {
            return;
        }

        $streak = $this->scores->streakDays($student);
        if ($streak < 2) {
            return;
        }

        $this->award(
            $student,
            PointsSource::Streak,
            "day:{$today}",
            $this->settingsFor($tenantId)->streak_day_points,
            $tenantId,
        );

        if ($streak >= 7) {
            $this->grantBadge($student, 'seven-day-streak', $tenantId);
        }
    }

    /**
     * Award a milestone badge once, celebrating it in-app for the earner and
     * (opt-out respected) their active batchmates. Never lets celebration
     * failures break the calling flow.
     */
    public function grantBadge(User $student, string $badge, int $tenantId): void
    {
        if (! is_string(config("points.badges.{$badge}"))) {
            return;
        }

        $created = false;
        try {
            $record = StudentBadge::query()->withoutGlobalScopes()->firstOrCreate(
                ['user_id' => $student->id, 'badge' => $badge],
                ['tenant_id' => $tenantId, 'awarded_at' => now()],
            );
            $created = $record->wasRecentlyCreated;
        } catch (QueryException) {
            return;
        }

        if (! $created) {
            return;
        }

        try {
            $label = (string) config("points.badges.{$badge}");

            InAppNotification::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $student->id,
                'title' => "Badge earned: {$label}",
                'body' => 'It\'s on your leaderboard card. Keep going.',
                'url' => '/dashboard',
            ]);

            // Batch-feed celebration — skipped entirely when the earner has
            // opted out of being named.
            if (! $student->leaderboard_opt_out) {
                $batchId = $this->activeBatchId($student);
                if ($batchId !== null) {
                    BatchMember::query()->withoutGlobalScopes()
                        ->where('batch_id', $batchId)
                        ->where('user_id', '!=', $student->id)
                        ->whereIn('status', array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying()))
                        ->limit((int) config('points.celebration_fanout_limit', 50))
                        ->pluck('user_id')
                        ->each(fn (int $mateId) => InAppNotification::query()->create([
                            'tenant_id' => $tenantId,
                            'user_id' => $mateId,
                            'title' => "{$student->name} earned {$label} 🎉",
                            'body' => 'Your batch is on the move — check the leaderboard.',
                            'url' => '/dashboard',
                        ]));
                }
            }
        } catch (Throwable $e) {
            Log::warning('Badge celebration failed', ['badge' => $badge, 'error' => $e->getMessage()]);
        }
    }

    /** The student's current occupying batch, if any. */
    public function activeBatchId(User $student): ?int
    {
        $member = BatchMember::query()->withoutGlobalScopes()
            ->where('user_id', $student->id)
            ->whereIn('status', array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying()))
            ->orderByDesc('id')
            ->first();

        return $member?->batch_id;
    }
}

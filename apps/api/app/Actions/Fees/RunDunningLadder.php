<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Enums\AccessBlockLevel;
use App\Enums\FeePlanStatus;
use App\Enums\InstalmentStatus;
use App\Models\AccessBlock;
use App\Models\FeePlan;
use App\Models\FeeReminder;
use App\Models\Instalment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Notifications\FeeNotifier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * The dunning engine (PRD §6.8), run daily by `fees:run-ladder`. For each active
 * fee plan's earliest unpaid instalment it: sends the pre-due / due / grace
 * reminder for today's rung (deduped via fee_reminders), applies a SOFT block
 * once the grace window closes, and promotes to a HARD block after the
 * hard-block window. Idempotent and safe to re-run any number of times per day.
 *
 * @phpstan-type LadderSummary array{reminders: int, soft_blocks: int, hard_blocks: int}
 */
final readonly class RunDunningLadder
{
    public function __construct(
        private ApplyAccessBlock $applyBlock,
        private FeeNotifier $notifier,
    ) {}

    /**
     * @return LadderSummary
     */
    public function handle(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $summary = ['reminders' => 0, 'soft_blocks' => 0, 'hard_blocks' => 0];

        $tenantIds = FeePlan::query()->withoutGlobalScopes()
            ->where('status', FeePlanStatus::Active->value)
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            app(TenantContext::class)->run($tenant, function () use ($today, &$summary): void {
                FeePlan::query()
                    ->where('status', FeePlanStatus::Active->value)
                    ->with(['instalments', 'student'])
                    ->get()
                    ->each(function (FeePlan $plan) use ($today, &$summary): void {
                        $this->processPlan($plan, $today, $summary);
                    });
            });
        }

        return $summary;
    }

    /**
     * @param  LadderSummary  $summary
     */
    private function processPlan(FeePlan $plan, Carbon $today, array &$summary): void
    {
        $next = $plan->instalments
            ->filter(fn (Instalment $i) => $i->status !== InstalmentStatus::Paid)
            ->sortBy('seq')
            ->first();

        $student = $plan->student;
        if ($next === null || $student === null) {
            return;
        }

        $reminderDays = (array) config('fees.ladder.reminder_days', [7, 3, 1, 0]);
        $graceDays = (int) config('fees.ladder.grace_days', 5);
        $hardAfter = (int) config('fees.ladder.hard_block_after_days', 7);

        $due = $next->due_on->copy()->startOfDay();
        $daysToDue = (int) $today->diffInDays($due, false); // >0 future, <=0 due/overdue
        $overdueDays = $daysToDue < 0 ? -$daysToDue : 0;

        // Pre-due + due-day reminders.
        if ($daysToDue >= 0 && in_array($daysToDue, $reminderDays, true)) {
            $rung = $daysToDue === 0 ? 'due' : "t-{$daysToDue}";
            $this->sendReminder($next, $student, $rung, $summary);
        }

        // Daily grace reminders (overdue days 1..graceDays).
        if ($overdueDays >= 1 && $overdueDays <= $graceDays) {
            $this->sendReminder($next, $student, "grace-{$overdueDays}", $summary);
        }

        // Soft block once the grace window closes; hard block after the window.
        if ($overdueDays >= $graceDays + $hardAfter) {
            $before = $this->activeLevel($student->id);
            $this->applyBlock->handle($student, AccessBlockLevel::Hard, $plan, "Overdue instalment {$next->seq}");
            if ($before !== 'hard') {
                $summary['hard_blocks']++;
            }
        } elseif ($overdueDays >= $graceDays) {
            $before = $this->activeLevel($student->id);
            $this->applyBlock->handle($student, AccessBlockLevel::Soft, $plan, "Overdue instalment {$next->seq}");
            if ($before === null) {
                $summary['soft_blocks']++;
            }
        }
    }

    /**
     * @param  LadderSummary  $summary
     */
    private function sendReminder(Instalment $instalment, User $student, string $rung, array &$summary): void
    {
        $reminder = FeeReminder::query()->firstOrCreate(
            ['instalment_id' => $instalment->id, 'rung' => $rung],
            ['tenant_id' => $instalment->tenant_id, 'channel' => 'log', 'sent_at' => now()],
        );

        if ($reminder->wasRecentlyCreated) {
            $this->notifier->reminder($student, $instalment, $rung, $instalment->payment_link_url);
            $summary['reminders']++;
        }
    }

    /** The student's highest active block level, or null. */
    private function activeLevel(int $userId): ?string
    {
        // `level` is an enum cast, so compare against enum instances.
        $levels = AccessBlock::query()
            ->where('user_id', $userId)
            ->whereNull('lifted_at')
            ->pluck('level');

        if ($levels->contains(AccessBlockLevel::Hard)) {
            return 'hard';
        }

        return $levels->contains(AccessBlockLevel::Soft) ? 'soft' : null;
    }
}

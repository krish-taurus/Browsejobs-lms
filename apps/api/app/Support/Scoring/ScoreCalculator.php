<?php

declare(strict_types=1);

namespace App\Support\Scoring;

use App\Enums\BatchMemberStatus;
use App\Enums\MasteryBand;
use App\Enums\NextActionKey;
use App\Enums\QuizAttemptStatus;
use App\Models\AccessBlock;
use App\Models\ActivityEvent;
use App\Models\Attendance;
use App\Models\BatchMember;
use App\Models\Instalment;
use App\Models\MentorSession;
use App\Models\MockInterview;
use App\Models\Module;
use App\Models\PriCalibration;
use App\Models\QuizAttempt;
use App\Models\TopicCompletion;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rule-based student scoring (PRD §6.4). Deterministic — turns the P3.1 telemetry
 * stream (+ completions, attendance, fees) into a mastery map, engagement/risk/PRI
 * scores, went-well/needs-work, and the single Next Best Action. Config-weighted;
 * no LLM. Runs inside the student's tenant context.
 *
 * @phpstan-type ScoreData array{engagement: int, risk_dropout: int, risk_placement: int, pri: int, streak_days: int, mastery: list<array{module_id: int, name: string, pct: int, band: string}>, went_well: list<string>, needs_work: list<string>, next_action: array{key: string, title: string, detail: string, href: string|null}, red_flags: list<string>}
 */
final class ScoreCalculator
{
    /** Whether the student has a completed AI mock — gates the mentor recommendation. */
    private bool $hasCompletedMock = false;

    /**
     * @return ScoreData
     */
    public function computeFor(User $student): array
    {
        return app(TenantContext::class)->run($student->tenant, function () use ($student): array {
            $mastery = $this->mastery($student);
            $engagement = $this->engagement($student);
            $streak = $this->streak($student);
            $attendance = $this->attendance($student);
            [$feeBlocked, $outstanding] = $this->fee($student);
            $stalled = $this->isStalled($student);

            $avgMastery = $mastery === [] ? 0 : (int) round(array_sum(array_column($mastery, 'pct')) / count($mastery));

            // Prefer the tenant's placement-outcome calibration (PRD §6.21); fall
            // back to config defaults when no calibration exists (small cohort).
            $priWeights = PriCalibration::activeWeights() ?? (array) config('scoring.pri.weights');
            $pri = (int) round(
                $avgMastery * (float) $priWeights['mastery']
                + $engagement * (float) $priWeights['engagement']
                + $attendance * (float) $priWeights['attendance'],
            );

            // PRD §6.6: a completed AI mock is real interview signal — blend the
            // best score in. No completed mock = PRI unchanged (P4.1).
            $bestMock = MockInterview::query()
                ->where('user_id', $student->id)
                ->where('status', MockInterview::STATUS_COMPLETED)
                ->max('overall_score');

            $this->hasCompletedMock = $bestMock !== null;

            if ($bestMock !== null) {
                $blend = (float) config('scoring.pri.mock_blend', 0.15);
                $pri = (int) round((1 - $blend) * $pri + $blend * (int) $bestMock);
            }

            // PRD §6.11: post-session mentor feedback calibrates PRI — human
            // judgment on top of the machine number. No feedback = unchanged.
            $mentorAvg = MentorSession::query()
                ->where('student_id', $student->id)
                ->whereNotNull('feedback_score')
                ->avg('feedback_score');

            if ($mentorAvg !== null) {
                $blend = (float) config('scoring.pri.mentor_blend', 0.10);
                $pri = (int) round((1 - $blend) * $pri + $blend * (float) $mentorAvg);
            }

            $riskDropout = (int) round(0.55 * (100 - $engagement) + 0.25 * ($stalled ? 100 : 0) + 0.20 * ($feeBlocked ? 100 : 0));
            $riskPlacement = (int) round(0.6 * (100 - $avgMastery) + 0.4 * (100 - $pri));

            $wentWell = $this->names($mastery, MasteryBand::Strong);
            $needsWork = $this->names($mastery, MasteryBand::Weak);

            return [
                'engagement' => $this->clamp($engagement),
                'risk_dropout' => $this->clamp($riskDropout),
                'risk_placement' => $this->clamp($riskPlacement),
                'pri' => $this->clamp($pri),
                'streak_days' => $streak,
                'mastery' => $mastery,
                'went_well' => $wentWell,
                'needs_work' => $needsWork,
                'next_action' => $this->nextAction($mastery, $engagement, $streak, $pri, $feeBlocked, $outstanding),
                'red_flags' => $this->redFlags($engagement, $attendance, $stalled, $feeBlocked),
            ];
        });
    }

    /**
     * @return list<array{module_id: int, name: string, pct: int, band: string}>
     */
    private function mastery(User $student): array
    {
        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        $courseIds = BatchMember::query()
            ->where('user_id', $student->id)
            ->whereIn('status', $occupying)
            ->with('batch:id,course_id')
            ->get()
            ->pluck('batch.course_id')
            ->filter()
            ->unique();

        if ($courseIds->isEmpty()) {
            return [];
        }

        $completed = TopicCompletion::query()->where('user_id', $student->id)->pluck('topic_id')->flip();
        $quizScores = $this->quizScores($student);
        $quizWeight = (float) config('scoring.mastery.quiz_weight', 0.4);

        return Module::query()
            ->whereIn('course_id', $courseIds)
            ->with('topics:id,module_id')
            ->orderBy('position')
            ->get()
            ->map(function (Module $module) use ($completed, $quizScores, $quizWeight): array {
                $topicIds = $module->topics->pluck('id');
                $total = $topicIds->count();
                $done = $total === 0 ? 0 : $topicIds->filter(fn ($id) => $completed->has($id))->count();
                $pct = $total === 0 ? 0 : (int) round($done / $total * 100);

                // Blend the module's latest submitted quiz score, if any (PRD §6.5).
                if (isset($quizScores[$module->id])) {
                    $pct = (int) round($pct * (1 - $quizWeight) + $quizScores[$module->id] * $quizWeight);
                }

                return [
                    'module_id' => $module->id,
                    'name' => $module->name,
                    'pct' => $pct,
                    'band' => MasteryBand::fromPct($pct)->value,
                ];
            })->all();
    }

    /**
     * Latest submitted quiz score per module for the student (PRD §6.5). Batch-loaded
     * to avoid N+1; empty when the student has taken no quizzes (so mastery is
     * unchanged from pure topic-completion).
     *
     * @return array<int, int> module_id => score_pct
     */
    private function quizScores(User $student): array
    {
        $scores = [];

        QuizAttempt::query()
            ->where('user_id', $student->id)
            ->where('status', QuizAttemptStatus::Submitted->value)
            ->with('quiz.lesson.topic:id,module_id')
            ->orderByDesc('submitted_at')
            ->get()
            ->each(function (QuizAttempt $attempt) use (&$scores): void {
                $moduleId = $attempt->quiz?->lesson?->topic?->module_id;
                if ($moduleId !== null && ! isset($scores[$moduleId])) {
                    $scores[$moduleId] = (int) $attempt->score_pct;
                }
            });

        return $scores;
    }

    private function engagement(User $student): int
    {
        $cfg = (array) config('scoring.engagement');
        $dates = $this->activeDates($student);

        if ($dates->isEmpty()) {
            return 0;
        }

        $daysSinceLast = (int) Carbon::parse($dates->first())->diffInDays(now());
        $recency = max(0.0, 1 - $daysSinceLast / (int) $cfg['recency_days']) * 100;

        $windowStart = now()->subDays((int) $cfg['consistency_window'])->toDateString();
        $activeInWindow = $dates->filter(fn ($d) => $d >= $windowStart)->count();
        $consistency = min($activeInWindow / 7, 1) * 100;

        $volumeStart = now()->subDays((int) $cfg['volume_window']);
        $volumeCount = ActivityEvent::query()->where('user_id', $student->id)->where('occurred_at', '>=', $volumeStart)->count();
        $volume = min($volumeCount / (int) $cfg['volume_target'], 1) * 100;

        $w = (array) $cfg['weights'];

        return (int) round($recency * (float) $w['recency'] + $consistency * (float) $w['consistency'] + $volume * (float) $w['volume']);
    }

    /**
     * Current consecutive-day activity streak. Public so the P3.6 points
     * engine awards streak points/badges from the same definition the coach
     * panel displays — one source of truth for "streak".
     */
    public function streakDays(User $student): int
    {
        return $this->streak($student);
    }

    private function streak(User $student): int
    {
        $dates = $this->activeDates($student)->values();
        if ($dates->isEmpty()) {
            return 0;
        }

        // A streak is only "live" if the latest activity was today or yesterday.
        $cursor = now()->startOfDay();
        if ($dates->first() !== $cursor->toDateString() && $dates->first() !== $cursor->copy()->subDay()->toDateString()) {
            return 0;
        }

        $set = $dates->flip();
        $cursor = Carbon::parse($dates->first());
        $streak = 0;
        while ($set->has($cursor->toDateString())) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    /**
     * Distinct active dates (YYYY-MM-DD), newest first.
     *
     * @return Collection<int, string>
     */
    private function activeDates(User $student)
    {
        return ActivityEvent::query()
            ->where('user_id', $student->id)
            ->orderByDesc('occurred_at')
            ->pluck('occurred_at')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();
    }

    private function attendance(User $student): int
    {
        return (int) round((float) Attendance::query()->where('user_id', $student->id)->avg('attended_pct'));
    }

    /**
     * @return array{0: bool, 1: int}
     */
    private function fee(User $student): array
    {
        $blocked = AccessBlock::query()->where('user_id', $student->id)->whereNull('lifted_at')->exists();

        $outstanding = (int) Instalment::query()
            ->whereHas('feePlan', fn ($q) => $q->where('user_id', $student->id))
            ->where('status', '!=', 'paid')
            ->where('due_on', '<', now())
            ->sum('amount_paise');

        return [$blocked || $outstanding > 0, $outstanding];
    }

    private function isStalled(User $student): bool
    {
        $last = TopicCompletion::query()->where('user_id', $student->id)->max('completed_at');
        if ($last === null) {
            return false; // a student who never started is captured by low engagement, not "stalled".
        }

        return Carbon::parse($last)->lt(now()->subDays((int) config('scoring.risk.stalled_days', 7)));
    }

    /**
     * @param  list<array{module_id: int, name: string, pct: int, band: string}>  $mastery
     * @return array{key: string, title: string, detail: string, href: string|null}
     */
    private function nextAction(array $mastery, int $engagement, int $streak, int $pri, bool $feeBlocked, int $outstanding): array
    {
        if ($feeBlocked) {
            return ['key' => NextActionKey::ClearFee->value, 'title' => 'Clear your dues to unlock classes & recordings', 'detail' => 'Your access is limited until the balance is settled.', 'href' => '/dashboard'];
        }

        // Persistent weakness (PRD §6.11): they practised (completed a mock)
        // and are still weak across 2+ modules — time for a human, not
        // another lap. Without a completed mock this never fires, so the
        // P3.2 action ladder is unchanged for everyone else.
        $weakModules = collect($mastery)->where('band', MasteryBand::Weak->value);
        if ($weakModules->count() >= 2 && $this->hasCompletedMock) {
            return ['key' => NextActionKey::BookMentor->value, 'title' => 'Book a mentor 1:1 — several modules need a human eye', 'detail' => 'A focused session beats another solo lap when more than one area is stuck.', 'href' => '/mentors'];
        }

        $weakest = $weakModules->sortBy('pct')->first();
        if ($weakest !== null) {
            return ['key' => NextActionKey::PracticeModule->value, 'title' => "Practice {$weakest['name']} — you're at {$weakest['pct']}%", 'detail' => 'A focused session here moves your PRI the most.', 'href' => '/labs'];
        }

        if ($engagement < (int) config('scoring.risk.low_engagement', 40) || $streak === 0) {
            return ['key' => NextActionKey::ReEngage->value, 'title' => 'Jump back in — a quick lab keeps your streak alive', 'detail' => 'Consistency is the biggest predictor of placement.', 'href' => '/labs'];
        }

        if ($pri >= (int) config('scoring.risk.placement_ready_pri', 70)) {
            return ['key' => NextActionKey::BookMock->value, 'title' => "You're on track — book a mock interview", 'detail' => 'Pressure-test your readiness before the real thing.', 'href' => '/mock'];
        }

        return ['key' => NextActionKey::NextTopic->value, 'title' => 'Complete your next topic', 'detail' => 'Keep the momentum going.', 'href' => '/classes'];
    }

    /**
     * @param  list<array{module_id: int, name: string, pct: int, band: string}>  $mastery
     * @return list<string>
     */
    private function names(array $mastery, MasteryBand $band): array
    {
        return collect($mastery)->where('band', $band->value)->pluck('name')->values()->all();
    }

    /**
     * @return list<string>
     */
    private function redFlags(int $engagement, int $attendance, bool $stalled, bool $feeBlocked): array
    {
        $flags = [];
        if ($feeBlocked) {
            $flags[] = 'fee_overdue';
        }
        if ($engagement < (int) config('scoring.risk.low_engagement', 40)) {
            $flags[] = 'low_engagement';
        }
        if ($attendance > 0 && $attendance < (int) config('scoring.risk.low_attendance', 60)) {
            $flags[] = 'low_attendance';
        }
        if ($stalled) {
            $flags[] = 'stalled';
        }

        return $flags;
    }

    private function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }
}

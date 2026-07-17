<?php

declare(strict_types=1);

namespace App\Actions\Quizzes;

use App\Jobs\CheckQuizCompletion;
use App\Models\QuizAttempt;

/**
 * Safety net behind the delayed CheckQuizCompletion jobs (PRD §6.5), mirroring the
 * ticket-SLA sweep: re-queues the idempotent ladder job for attempts whose 48h/96h
 * marks have passed but weren't handled (e.g. after downtime). Runs hourly.
 *
 * @phpstan-type SweepSummary array{reminders: int, flags: int}
 */
final readonly class RunQuizDispatchSweep
{
    /**
     * @return SweepSummary
     */
    public function handle(): array
    {
        $summary = ['reminders' => 0, 'flags' => 0];

        QuizAttempt::query()->withoutGlobalScopes()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNull('reminded_at')
            ->where('created_at', '<=', now()->subHours(48))
            ->pluck('id')
            ->each(function (int $id) use (&$summary): void {
                CheckQuizCompletion::dispatch($id, 'reminder');
                $summary['reminders']++;
            });

        QuizAttempt::query()->withoutGlobalScopes()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNull('flagged_at')
            ->where('created_at', '<=', now()->subHours(96))
            ->pluck('id')
            ->each(function (int $id) use (&$summary): void {
                CheckQuizCompletion::dispatch($id, 'flag');
                $summary['flags']++;
            });

        return $summary;
    }
}

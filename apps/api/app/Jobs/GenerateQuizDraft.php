<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Quizzes\GenerateQuiz;
use App\Enums\QuizStatus;
use App\Models\Quiz;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiBudgetExceeded;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued AI quiz generation (PRD §6.5; every AI call is a queued job per CLAUDE.md).
 * Bills the triggering staff user's token budget. Idempotency: skips an
 * already-approved quiz. A budget/parse failure leaves the quiz a draft (no
 * crash-loop) — the trainer can retry.
 */
final class GenerateQuizDraft implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $quizId,
        public readonly int $actorUserId,
        public readonly int $count = 8,
    ) {}

    public function handle(GenerateQuiz $generate): void
    {
        $quiz = Quiz::query()->withoutGlobalScopes()->find($this->quizId);
        if ($quiz === null || $quiz->status === QuizStatus::Approved) {
            return;
        }

        $tenant = Tenant::query()->find($quiz->tenant_id);
        $actor = User::query()->withoutGlobalScopes()->find($this->actorUserId);
        if ($tenant === null || $actor === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($quiz, $actor, $generate): void {
            try {
                $generate->handle($quiz, $actor, $this->count);
            } catch (AiBudgetExceeded|Throwable $e) {
                // Leave the quiz as a draft; the trainer retries. Do not fail the job loop.
            }
        });
    }
}

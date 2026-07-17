<?php

declare(strict_types=1);

namespace App\Actions\Quizzes;

use App\Enums\QuizStatus;
use App\Models\Quiz;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Trainer approval of a quiz (PRD §6.5 / §7 human checkpoint). Only an approved
 * quiz dispatches on ModuleCompleted. Idempotent; audited. Mirrors ApproveTestimonial.
 */
final readonly class ApproveQuiz
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Quiz $quiz, ?User $actor = null): Quiz
    {
        return app(TenantContext::class)->run($quiz->tenant, function () use ($quiz, $actor): Quiz {
            if ($quiz->status === QuizStatus::Approved) {
                return $quiz;
            }

            if (! $quiz->questions()->exists()) {
                throw ValidationException::withMessages(['questions' => 'A quiz needs at least one question before approval.']);
            }

            $quiz->update([
                'status' => QuizStatus::Approved->value,
                'approved_by' => $actor?->id,
                'approved_at' => now(),
            ]);

            $this->audit->log(
                action: 'quiz.approved',
                target: $quiz,
                metadata: ['lesson_id' => $quiz->lesson_id, 'questions' => $quiz->questions()->count()],
                actor: $actor,
            );

            return $quiz->refresh();
        });
    }
}

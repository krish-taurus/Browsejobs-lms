<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\Quizzes\SubmitQuizAttempt;
use App\Enums\QuizAttemptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\SubmitQuizRequest;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The student's timed MCQ (PRD §6.5). Under auth:sanctum without tenant.user, so work
 * wraps in the student's tenant context. The timer is server-authoritative and the
 * correct answers are never sent before submission.
 */
final class MyQuizController extends Controller
{
    /**
     * Every quiz the student has been given — the portal's Quizzes page.
     *
     * An attempt row IS the assignment: it is created when a trainer opens the
     * quiz for the batch, so listing attempts lists exactly what this student
     * may sit. Without this the only way in was the WhatsApp/email magic link,
     * which is lost the moment that message is missed.
     */
    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $rows = QuizAttempt::query()
                ->where('user_id', $request->user()->id)
                ->with(['quiz' => fn ($q) => $q->withCount('questions')->with('lesson.topic.module.course')])
                ->get()
                ->filter(fn (QuizAttempt $attempt): bool => $attempt->quiz !== null)
                ->map(function (QuizAttempt $attempt): array {
                    $quiz = $attempt->quiz;
                    $module = $quiz->lesson?->topic?->module;

                    return [
                        'attempt_id' => $attempt->id,
                        'title' => $quiz->title,
                        'course' => $module?->course?->name,
                        'module' => $module?->name,
                        'questions' => $quiz->questions_count,
                        'minutes' => (int) round($quiz->time_limit_sec / 60),
                        'pass_pct' => $quiz->pass_pct,
                        'status' => $this->displayStatus($attempt),
                        'due_at' => $attempt->due_at?->toIso8601String(),
                        'deadline_at' => $attempt->deadline_at?->toIso8601String(),
                        'score_pct' => $attempt->score_pct,
                        'passed' => $attempt->score_pct === null ? null : $attempt->score_pct >= $quiz->pass_pct,
                        'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                    ];
                });

            // Anything still to sit comes first, soonest due date at the top;
            // the finished ones follow, most recently submitted first.
            $open = $rows->whereIn('status', ['pending', 'in_progress'])
                ->sortBy(fn (array $row): string => $row['due_at'] ?? '9999')
                ->values();

            $done = $rows->whereNotIn('status', ['pending', 'in_progress'])
                ->sortByDesc(fn (array $row): string => $row['submitted_at'] ?? '')
                ->values();

            return response()->json(['data' => $open->concat($done)->all()]);
        });
    }

    public function show(Request $request, int $attempt): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $attempt): JsonResponse {
            $model = $this->ownedAttempt($request, $attempt);
            $quiz = $model->quiz;

            // Start the clock on first open; expire if the deadline already passed.
            if ($model->status === QuizAttemptStatus::Pending) {
                $model->update([
                    'status' => QuizAttemptStatus::InProgress->value,
                    'started_at' => now(),
                    'deadline_at' => now()->addSeconds($quiz->time_limit_sec),
                ]);
            } elseif ($model->status === QuizAttemptStatus::InProgress && $model->isLate()) {
                $model->update(['status' => QuizAttemptStatus::Expired->value]);
            }

            return response()->json(['data' => [
                'attempt_id' => $model->id,
                'status' => $model->status->value,
                'title' => $quiz->title,
                'instructions' => $quiz->instructions,
                'pass_pct' => $quiz->pass_pct,
                'deadline_at' => $model->deadline_at?->toIso8601String(),
                'questions' => $this->studentQuestions($quiz, $model),
            ]]);
        });
    }

    public function submit(SubmitQuizRequest $request, int $attempt, SubmitQuizAttempt $submit): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $attempt, $submit): JsonResponse {
            $model = $this->ownedAttempt($request, $attempt);

            /** @var array<string, int> $answers */
            $answers = $request->input('answers', []);
            /** @var array<string, int> $integrity */
            $integrity = $request->input('integrity', []);

            $graded = $submit->handle($model, $answers, $integrity);

            return response()->json(['data' => [
                'attempt_id' => $graded->id,
                'status' => $graded->status->value,
                'score_pct' => $graded->score_pct,
                'correct_count' => $graded->correct_count,
                'total_count' => $graded->total_count,
                'passed' => ($graded->score_pct ?? 0) >= $graded->quiz->pass_pct,
                'review' => $this->reviewPayload($graded),
            ]]);
        });
    }

    /**
     * What the list should say. An in-progress attempt whose timer has run out
     * is really expired — `show()` is what writes that state, so this only
     * reports it and loading the list never mutates an attempt.
     */
    private function displayStatus(QuizAttempt $attempt): string
    {
        if ($attempt->status === QuizAttemptStatus::InProgress && $attempt->isLate()) {
            return QuizAttemptStatus::Expired->value;
        }

        return $attempt->status->value;
    }

    private function ownedAttempt(Request $request, int $attemptId): QuizAttempt
    {
        return QuizAttempt::query()
            ->where('user_id', $request->user()->id)
            ->with('quiz')
            ->findOrFail($attemptId);
    }

    /**
     * Questions in a deterministic per-attempt shuffle, WITHOUT the correct answer.
     *
     * @return list<array{id: int, prompt: string, options: array<int, string>}>
     */
    private function studentQuestions(Quiz $quiz, QuizAttempt $attempt): array
    {
        $questions = $quiz->questions()->get();

        if ($quiz->shuffle) {
            $questions = $questions->sortBy(fn (QuizQuestion $q) => md5($q->id.':'.$attempt->id))->values();
        }

        return $questions->map(fn (QuizQuestion $q): array => [
            'id' => $q->id,
            'prompt' => $q->prompt,
            'options' => $q->options,
        ])->all();
    }

    /**
     * Post-submit review (correctness + explanations are only revealed here).
     *
     * @return list<array{id: int, correct_index: int, chosen: int|null, is_correct: bool, explanation: string|null}>
     */
    private function reviewPayload(QuizAttempt $attempt): array
    {
        $answers = $attempt->answers ?? [];

        return $attempt->quiz->questions()->get()->map(function (QuizQuestion $q) use ($answers): array {
            $chosen = array_key_exists((string) $q->id, $answers) ? (int) $answers[(string) $q->id] : null;

            return [
                'id' => $q->id,
                'correct_index' => $q->correct_index,
                'chosen' => $chosen,
                'is_correct' => $chosen === $q->correct_index,
                'explanation' => $q->explanation,
            ];
        })->all();
    }
}

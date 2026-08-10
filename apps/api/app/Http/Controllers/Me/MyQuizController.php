<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\Quizzes\SubmitQuizAttempt;
use App\Enums\BatchMemberStatus;
use App\Enums\QuizAttemptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\SubmitQuizRequest;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Support\Points\PointsService;
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
     * How this student's batch did on one quiz (PRD §6.16 leaderboard rules).
     *
     * Ranked against their own batch only — a cohort taught weeks apart is not
     * a fair comparison, and a school-wide table is just noise. Same anti-
     * toxicity rules as the points leaderboard: the top ten are named unless
     * they opted out, and your own row is always named to you.
     *
     * Open before the student has sat it as well — seeing where the batch got
     * to is the motivation to do better, and a board of names and percentages
     * gives away no questions or answers.
     */
    public function leaderboard(Request $request, int $attempt, PointsService $points): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $attempt, $points): JsonResponse {
            $user = $request->user();
            $mine = $this->ownedAttempt($request, $attempt);

            $batchId = $points->activeBatchId($user);

            $mateIds = $batchId === null ? [$user->id] : BatchMember::query()
                ->where('batch_id', $batchId)
                ->whereIn('status', array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying()))
                ->pluck('user_id')
                ->all();

            $ranked = QuizAttempt::query()
                ->where('quiz_id', $mine->quiz_id)
                ->whereIn('user_id', $mateIds)
                ->where('status', QuizAttemptStatus::Submitted->value)
                ->with('student:id,name,leaderboard_opt_out')
                // Highest score first; among equal scores, whoever finished first.
                ->orderByDesc('score_pct')
                ->orderBy('submitted_at')
                ->get();

            $myIndex = $ranked->search(fn (QuizAttempt $row): bool => $row->user_id === $user->id);

            $top = $ranked->take(10)->values()->map(function (QuizAttempt $row, int $i) use ($user): array {
                $isMe = $row->user_id === $user->id;
                $named = $row->student !== null && ! $row->student->leaderboard_opt_out;

                return [
                    'rank' => $i + 1,
                    'name' => $isMe ? $user->name : ($named ? $row->student->name : 'Student'),
                    'score_pct' => $row->score_pct,
                    'correct_count' => $row->correct_count,
                    'total_count' => $row->total_count,
                    'is_me' => $isMe,
                ];
            });

            $me = null;
            if ($myIndex !== false) {
                $myScore = (int) ($ranked[$myIndex]->score_pct ?? 0);
                $toNext = $myIndex === 0 ? 0 : max(0, (int) $ranked[$myIndex - 1]->score_pct - $myScore);

                $me = [
                    'rank' => $myIndex + 1,
                    'score_pct' => $ranked[$myIndex]->score_pct,
                    'passed' => $myScore >= ($mine->quiz?->pass_pct ?? 0),
                    'to_next' => $toNext,
                    'coach_line' => $this->coachLine($myIndex, $toNext, $ranked->count()),
                ];
            }

            return response()->json(['data' => [
                'quiz' => $mine->quiz?->title,
                'pass_pct' => $mine->quiz?->pass_pct,
                'batch' => $batchId === null ? null : Batch::query()->whereKey($batchId)->value('number'),
                'participants' => $ranked->count(),
                'top' => $top->all(),
                // Lets the page offer Start / Resume when the quiz is still open.
                'my_status' => $this->displayStatus($mine),
                'best_score' => $ranked->first()?->score_pct,
                'me' => $me,
            ]]);
        });
    }

    /**
     * One line of encouragement, pointed at the next rank up rather than at the
     * top — a gap you could actually close is what makes a board motivating.
     */
    private function coachLine(int $index, int $toNext, int $participants): string
    {
        if ($index === 0) {
            return $participants > 1
                ? 'You top your batch on this one — hold it on the next quiz.'
                : 'First one in. See how the rest of your batch does.';
        }

        if ($toNext === 0) {
            return sprintf('Level with #%d — finish faster next time and the place is yours.', $index);
        }

        return sprintf('%d%% from #%d. One more correct answer next time closes most of that.', $toNext, $index);
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

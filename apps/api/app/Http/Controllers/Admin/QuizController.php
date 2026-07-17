<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Quizzes\ApproveQuiz;
use App\Enums\LessonType;
use App\Enums\QuizStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quizzes\GenerateQuizRequest;
use App\Http\Requests\Quizzes\UpsertQuizQuestionRequest;
use App\Http\Requests\Quizzes\UpsertQuizRequest;
use App\Http\Resources\QuizResource;
use App\Jobs\GenerateQuizDraft;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin/trainer quiz builder + approval (PRD §6.5). Gated by `can:manage-curriculum`
 * (trainers hold it). Editing an approved quiz reverts it to draft so a stale quiz
 * never dispatches without re-approval.
 */
final class QuizController extends Controller
{
    public function index(): JsonResponse
    {
        $lessons = Lesson::query()
            ->where('type', LessonType::Quiz->value)
            ->with(['topic:id,name,module_id', 'topic.module:id,name,course_id', 'topic.module.course:id,name'])
            ->orderBy('id')
            ->get();

        $quizzes = Quiz::query()->whereIn('lesson_id', $lessons->pluck('id'))->get()->keyBy('lesson_id');

        return response()->json(['data' => $lessons->map(function (Lesson $lesson) use ($quizzes): array {
            $quiz = $quizzes->get($lesson->id);

            return [
                'lesson_id' => $lesson->id,
                'title' => $lesson->title,
                'course' => $lesson->topic?->module?->course?->name,
                'module' => $lesson->topic?->module?->name,
                'has_quiz' => $quiz !== null,
                'status' => $quiz?->status->value,
                'source' => $quiz?->source->value,
                'question_count' => $quiz?->questions()->count() ?? 0,
            ];
        })->all()]);
    }

    public function show(Lesson $lesson): JsonResponse
    {
        $quiz = Quiz::query()->where('lesson_id', $lesson->id)->with('questions')->first();

        return response()->json(['data' => $quiz === null ? null : new QuizResource($quiz)]);
    }

    public function upsert(UpsertQuizRequest $request, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->type === LessonType::Quiz, 422, 'Lesson is not a quiz.');

        $quiz = Quiz::query()->firstOrNew(['lesson_id' => $lesson->id]);
        $quiz->fill([
            'tenant_id' => $lesson->tenant_id,
            'title' => $request->string('title')->toString(),
            'instructions' => $request->input('instructions'),
            'time_limit_sec' => $request->integer('time_limit_sec') ?: ($quiz->time_limit_sec ?: 600),
            'pass_pct' => $request->integer('pass_pct') ?: ($quiz->pass_pct ?: 60),
            'shuffle' => $request->boolean('shuffle', $quiz->shuffle ?? true),
        ]);
        $quiz->save();

        return (new QuizResource($quiz->load('questions')))->response();
    }

    public function generate(GenerateQuizRequest $request, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->type === LessonType::Quiz, 422, 'Lesson is not a quiz.');

        $quiz = Quiz::query()->firstOrCreate(
            ['lesson_id' => $lesson->id],
            ['tenant_id' => $lesson->tenant_id, 'title' => $lesson->title],
        );

        GenerateQuizDraft::dispatch($quiz->id, $request->user()->id, $request->integer('count') ?: 8);

        return response()->json(['data' => ['status' => 'generating', 'quiz_id' => $quiz->id]], 202);
    }

    public function storeQuestion(UpsertQuizQuestionRequest $request, Quiz $quiz): JsonResponse
    {
        $question = QuizQuestion::query()->create([
            'tenant_id' => $quiz->tenant_id,
            'quiz_id' => $quiz->id,
            'prompt' => $request->string('prompt')->toString(),
            'options' => array_values($request->input('options')),
            'correct_index' => $request->integer('correct_index'),
            'explanation' => $request->input('explanation'),
            'position' => $request->integer('position') ?: $quiz->questions()->count(),
        ]);

        $this->revertToDraft($quiz);

        return (new QuizResource($quiz->load('questions')))->response()->setStatusCode(201);
    }

    public function updateQuestion(UpsertQuizQuestionRequest $request, QuizQuestion $question): JsonResponse
    {
        $question->update([
            'prompt' => $request->string('prompt')->toString(),
            'options' => array_values($request->input('options')),
            'correct_index' => $request->integer('correct_index'),
            'explanation' => $request->input('explanation'),
            'position' => $request->integer('position') ?: $question->position,
        ]);

        $quiz = $question->quiz;
        $this->revertToDraft($quiz);

        return (new QuizResource($quiz->load('questions')))->response();
    }

    public function destroyQuestion(QuizQuestion $question): JsonResponse
    {
        $quiz = $question->quiz;
        $question->delete();
        $this->revertToDraft($quiz);

        return response()->json(status: 204);
    }

    public function approve(Request $request, Quiz $quiz, ApproveQuiz $approve): JsonResponse
    {
        $approve->handle($quiz, $request->user());

        return (new QuizResource($quiz->refresh()->load('questions')))->response();
    }

    /** Any edit un-approves the quiz so a stale version never dispatches. */
    private function revertToDraft(Quiz $quiz): void
    {
        if ($quiz->status === QuizStatus::Approved) {
            $quiz->update(['status' => QuizStatus::Draft->value, 'approved_by' => null, 'approved_at' => null]);
        }
    }
}

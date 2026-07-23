<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Content\GenerateSimpleNotes;
use App\Actions\Curriculum\GenerateDayPlan;
use App\Actions\Quizzes\GenerateTopicAssignment;
use App\Actions\Quizzes\RenderQuizPdf;
use App\Events\CurriculumChanged;
use App\Http\Controllers\Controller;
use App\Models\LessonNote;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Day-by-day syllabus builder (ADR 0049). A trainer plans a module one teaching
 * day at a time — typed manually or drafted by AI from keywords — then attaches
 * each day's beginner notes PDF and MCQ assignment (AI-generated or uploaded).
 * All AI output lands as drafts behind the existing approve flows (PRD §7).
 */
final class DayBuilderController extends Controller
{
    public function index(Module $module): JsonResponse
    {
        $module->loadMissing('course');

        $topics = $module->topics()
            ->orderByRaw('COALESCE(day_number, position + 1)')
            ->orderBy('position')
            ->with('lessons')
            ->get();

        $lessonIds = $topics->flatMap->lessons->pluck('id');
        $notes = LessonNote::query()->whereIn('lesson_id', $lessonIds)->get()->keyBy('lesson_id');
        $quizzes = Quiz::query()->whereIn('lesson_id', $lessonIds)->withCount('questions')->get()->keyBy('lesson_id');

        $days = $topics->map(function (Topic $topic) use ($notes, $quizzes): array {
            $noteLesson = $topic->lessons->first(fn ($l) => $l->type->value === 'notes');
            $quizLesson = $topic->lessons->first(fn ($l) => $l->type->value === 'quiz');
            $note = $noteLesson !== null ? $notes->get($noteLesson->id) : null;
            $quiz = $quizLesson !== null ? $quizzes->get($quizLesson->id) : null;

            return [
                'id' => $topic->id,
                'day_number' => $topic->day_number,
                'name' => $topic->name,
                'summary' => $topic->summary,
                'keywords' => $topic->keywords ?? [],
                'notes' => $note === null ? null : [
                    'lesson_id' => $note->lesson_id,
                    'status' => $note->status->value,
                    'has_content' => $note->hasNotes(),
                    'has_pdf' => filled($note->pdf_path),
                    'pdf_uploaded' => (bool) $note->pdf_uploaded,
                ],
                'assignment' => $quiz === null ? null : [
                    'lesson_id' => $quiz->lesson_id,
                    'quiz_id' => $quiz->id,
                    'status' => $quiz->status->value,
                    'questions' => (int) $quiz->questions_count,
                    'has_pdf' => filled($quiz->pdf_path),
                    'pdf_uploaded' => (bool) $quiz->pdf_uploaded,
                ],
            ];
        });

        return response()->json(['data' => [
            'module' => ['id' => $module->id, 'name' => $module->name, 'course' => $module->course?->name, 'course_id' => $module->course_id],
            'days' => $days,
        ]]);
    }

    /** AI-draft one day from keywords. Returns a draft only — nothing persists. */
    public function plan(Request $request, Module $module, GenerateDayPlan $generate): JsonResponse
    {
        $data = $request->validate([
            'keywords' => ['required', 'array', 'min:1', 'max:12'],
            'keywords.*' => ['string', 'max:60'],
            'day_number' => ['required', 'integer', 'between:1,365'],
        ]);

        $draft = $generate->handle($module, $request->user(), $data['keywords'], (int) $data['day_number']);

        if ($draft === null) {
            return response()->json([
                'error' => ['code' => 'day_plan_failed', 'message' => 'The day plan could not be generated. Try again or write it manually.'],
            ], 422);
        }

        return response()->json(['data' => $draft]);
    }

    /** Save a day — the manual path, and where a reviewed AI draft is committed. */
    public function store(Request $request, Module $module): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'day_number' => ['required', 'integer', 'between:1,365',
                Rule::unique('topics')->where('module_id', $module->id)],
            'summary' => ['nullable', 'string', 'max:5000'],
            'keywords' => ['nullable', 'array', 'max:12'],
            'keywords.*' => ['string', 'max:60'],
        ]);

        $topic = Topic::query()->create([
            'tenant_id' => $module->tenant_id,
            'module_id' => $module->id,
            'name' => $data['name'],
            'position' => $module->topics()->count(),
            'day_number' => $data['day_number'],
            'summary' => $data['summary'] ?? null,
            'keywords' => $data['keywords'] ?? null,
        ]);

        CurriculumChanged::dispatch($module->course_id, (int) $module->tenant_id);

        return response()->json(['data' => $topic], 201);
    }

    /** Generate the day's beginner notes as a draft on its notes lesson. */
    public function simpleNotes(Request $request, Topic $topic, GenerateSimpleNotes $generate): JsonResponse
    {
        $note = $generate->handle($topic, $request->user());

        if ($note === null) {
            return response()->json([
                'error' => ['code' => 'notes_failed', 'message' => 'The notes could not be generated. Try again.'],
            ], 422);
        }

        return response()->json(['data' => [
            'lesson_id' => $note->lesson_id,
            'status' => $note->status->value,
            'notes' => $note->notes,
        ]], 201);
    }

    /** Generate the day's MCQ assignment as a draft quiz. */
    public function assignment(Request $request, Topic $topic, GenerateTopicAssignment $generate): JsonResponse
    {
        $count = (int) $request->input('count', 8);
        abort_unless($count >= 3 && $count <= 20, 422, 'Question count must be 3–20.');

        $result = $generate->handle($topic, $request->user(), $count);

        if ($result['written'] === 0) {
            return response()->json([
                'error' => ['code' => 'assignment_failed', 'message' => 'No questions could be generated. Try again or write them in the quiz builder.'],
            ], 422);
        }

        return response()->json(['data' => [
            'quiz_id' => $result['quiz']->id,
            'lesson_id' => $result['quiz']->lesson_id,
            'questions' => $result['written'],
            'status' => $result['quiz']->status->value,
        ]], 201);
    }

    /** Render the assignment paper PDF from the quiz's current questions. */
    public function renderPdf(Quiz $quiz, RenderQuizPdf $render): JsonResponse
    {
        abort_unless($quiz->questions()->exists(), 422, 'The quiz has no questions to render.');

        return response()->json(['data' => ['path' => $render->handle($quiz)]]);
    }

    /** Manually attach an assignment paper (mentor-authored PDF). */
    public function uploadPdf(Request $request, Quiz $quiz): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:pdf', 'max:20480']]);

        $path = $request->file('file')->storeAs(
            "assignments/{$quiz->tenant_id}",
            "lesson-{$quiz->lesson_id}-uploaded.pdf",
            ['disk' => RenderQuizPdf::DISK, 'visibility' => 'private'],
        );

        $quiz->update(['pdf_path' => $path, 'pdf_uploaded' => true]);

        return response()->json(['data' => ['path' => $path, 'uploaded' => true]]);
    }

    /** Short-lived download link for the assignment paper. */
    public function downloadPdf(Quiz $quiz): JsonResponse
    {
        abort_if(blank($quiz->pdf_path), 404, 'No assignment PDF yet.');

        return response()->json(['data' => [
            'url' => Storage::disk(RenderQuizPdf::DISK)->temporaryUrl((string) $quiz->pdf_path, now()->addMinutes(15)),
        ]]);
    }
}

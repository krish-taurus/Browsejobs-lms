<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Assignments\GenerateAssignmentBrief;
use App\Enums\LessonType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assignments\UpsertAssignmentRequest;
use App\Models\Assignment;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin/trainer assignment builder (PRD §6.5). Gated by `can:manage-curriculum`. The
 * rubric drives AI grading.
 */
final class AssignmentController extends Controller
{
    public function index(): JsonResponse
    {
        $lessons = Lesson::query()
            ->whereIn('type', [LessonType::Assignment->value, LessonType::Project->value])
            ->with(['topic:id,name,module_id', 'topic.module:id,name,course_id', 'topic.module.course:id,name'])
            ->orderBy('id')
            ->get();

        $assignments = Assignment::query()->whereIn('lesson_id', $lessons->pluck('id'))->get()->keyBy('lesson_id');

        return response()->json(['data' => $lessons->map(function (Lesson $lesson) use ($assignments): array {
            $assignment = $assignments->get($lesson->id);

            return [
                'lesson_id' => $lesson->id,
                'title' => $lesson->title,
                'course' => $lesson->topic?->module?->course?->name,
                'module' => $lesson->topic?->module?->name,
                'has_assignment' => $assignment !== null,
                'criteria_count' => count($assignment?->rubric ?? []),
            ];
        })->all()]);
    }

    public function show(Lesson $lesson): JsonResponse
    {
        $assignment = Assignment::query()->where('lesson_id', $lesson->id)->first();

        return response()->json(['data' => $assignment === null ? null : $this->payload($assignment)]);
    }

    /**
     * AI-draft an assignment brief + rubric from the lesson's module. Returns
     * the draft only — the trainer reviews and saves it via upsert.
     */
    public function generate(Request $request, Lesson $lesson, GenerateAssignmentBrief $generate): JsonResponse
    {
        abort_unless(
            in_array($lesson->type, [LessonType::Assignment, LessonType::Project], true),
            422,
            'Lesson is not an assignment.',
        );

        $draft = $generate->handle($lesson, $request->user());

        if ($draft === null) {
            return response()->json(['error' => [
                'code' => 'generation_failed',
                'message' => 'The assignment could not be generated. Try again.',
                'details' => null,
            ]], 422);
        }

        return response()->json(['data' => $draft]);
    }

    public function upsert(UpsertAssignmentRequest $request, Lesson $lesson): JsonResponse
    {
        abort_unless(
            in_array($lesson->type, [LessonType::Assignment, LessonType::Project], true),
            422,
            'Lesson is not an assignment.',
        );

        $rubric = array_map(
            fn (array $r) => ['criterion' => (string) $r['criterion'], 'max_points' => (int) $r['max_points']],
            $request->input('rubric'),
        );

        $assignment = Assignment::query()->updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'tenant_id' => $lesson->tenant_id,
                'title' => $request->string('title')->toString(),
                'instructions' => $request->input('instructions'),
                'rubric' => $rubric,
                'max_points' => array_sum(array_column($rubric, 'max_points')),
                'allow_link' => $request->boolean('allow_link', true),
                'allow_file' => $request->boolean('allow_file', true),
            ],
        );

        return response()->json(['data' => $this->payload($assignment)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Assignment $assignment): array
    {
        return [
            'lesson_id' => $assignment->lesson_id,
            'title' => $assignment->title,
            'instructions' => $assignment->instructions,
            'rubric' => $assignment->rubric ?? [],
            'max_points' => $assignment->max_points,
            'allow_link' => $assignment->allow_link,
            'allow_file' => $assignment->allow_file,
        ];
    }
}

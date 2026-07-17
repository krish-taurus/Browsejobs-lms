<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Assignments\DispatchAssignmentToBatch;
use App\Actions\Mocks\DispatchMockToBatch;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Lesson;
use App\Models\MockBlueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Send a specific mock or assignment to a batch (PRD §6.5/§6.6). Gated `can:teach-classes`
 * — the trainer who took the class sends the mock/assignment for it. Options are scoped to
 * the batch's course so you can only send what belongs to it.
 */
final class BatchDispatchController extends Controller
{
    public function options(Batch $batch): JsonResponse
    {
        $mocks = MockBlueprint::query()
            ->where('course_id', $batch->course_id)
            ->where('is_active', true)
            ->orderBy('skill')
            ->get(['id', 'skill', 'role_title']);

        $assignments = Assignment::query()
            ->whereHas('lesson.topic.module', fn ($q) => $q->where('course_id', $batch->course_id))
            ->with('lesson:id,title')
            ->get()
            ->map(fn (Assignment $a) => ['lesson_id' => $a->lesson_id, 'title' => $a->lesson?->title]);

        return response()->json(['mocks' => $mocks, 'assignments' => $assignments]);
    }

    public function sendMock(Request $request, Batch $batch, DispatchMockToBatch $dispatch): JsonResponse
    {
        $blueprint = MockBlueprint::query()
            ->where('course_id', $batch->course_id)
            ->find((int) $request->integer('blueprint_id'));

        if ($blueprint === null) {
            throw new NotFoundHttpException; // not a mock for this batch's course
        }

        $count = $dispatch->handle($batch, $blueprint, $request->user());

        return response()->json(['data' => ['notified' => $count]]);
    }

    public function sendAssignment(Request $request, Batch $batch, DispatchAssignmentToBatch $dispatch): JsonResponse
    {
        $lessonId = (int) $request->integer('lesson_id');

        // The lesson must carry an assignment and belong to this batch's course.
        $lesson = Lesson::query()
            ->whereHas('assignment')
            ->whereHas('topic.module', fn ($q) => $q->where('course_id', $batch->course_id))
            ->find($lessonId);

        if ($lesson === null) {
            throw new NotFoundHttpException;
        }

        $count = $dispatch->handle($batch, $lesson, $request->user());

        return response()->json(['data' => ['notified' => $count]]);
    }
}

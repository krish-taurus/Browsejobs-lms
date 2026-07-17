<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Enums\NoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The student's study notes for a lesson (PRD §6.10). Under auth:sanctum without
 * tenant.user, so it wraps in the student's tenant context. Only APPROVED notes are
 * returned, and the raw transcript is NEVER exposed to students.
 */
final class MyLessonNotesController extends Controller
{
    public function show(Request $request, int $lesson): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($lesson): JsonResponse {
            $note = LessonNote::query()
                ->where('lesson_id', $lesson)
                ->where('status', NoteStatus::Approved->value)
                ->with('lesson:id,title')
                ->firstOrFail();

            return response()->json(['data' => [
                'lesson_id' => $note->lesson_id,
                'title' => $note->lesson?->title,
                'notes' => $note->notes,
                'approved_at' => $note->approved_at?->toIso8601String(),
            ]]);
        });
    }
}

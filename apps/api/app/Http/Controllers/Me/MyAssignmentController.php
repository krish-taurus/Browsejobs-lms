<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\Assignments\SubmitAssignment;
use App\Enums\GradeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\SubmitAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentGrade;
use App\Models\AssignmentSubmission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The student's assignment surface (PRD §6.5). Under auth:sanctum without tenant.user,
 * so work wraps in the student's tenant context. A grade is exposed ONLY once the
 * trainer has released it, and the AI-likelihood signal is NEVER sent to the student.
 */
final class MyAssignmentController extends Controller
{
    public function show(Request $request, int $lesson): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $lesson): JsonResponse {
            $assignment = Assignment::query()->where('lesson_id', $lesson)->firstOrFail();
            $submission = $this->ownSubmission($request, $assignment);

            return response()->json(['data' => [
                'lesson_id' => $lesson,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'allow_link' => $assignment->allow_link,
                'allow_file' => $assignment->allow_file,
                'max_points' => $assignment->max_points,
                'rubric' => array_map(fn (array $r) => ['criterion' => $r['criterion'] ?? '', 'max_points' => $r['max_points'] ?? 0], $assignment->rubric ?? []),
                'submission' => $submission === null ? null : [
                    'body' => $submission->body,
                    'link' => $submission->link,
                    'file' => $this->file($submission),
                    'status' => $submission->status->value,
                    'submitted_at' => $submission->submitted_at?->toIso8601String(),
                ],
                'grade' => $this->releasedGrade($submission),
            ]]);
        });
    }

    public function submit(SubmitAssignmentRequest $request, int $lesson, SubmitAssignment $submit): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $lesson, $submit): JsonResponse {
            $assignment = Assignment::query()->where('lesson_id', $lesson)->firstOrFail();

            $submit->handle(
                $request->user(),
                $assignment,
                $request->input('body'),
                $request->input('link'),
                $request->file('file'),
            );

            return response()->json(['data' => ['status' => 'submitted']], 201);
        });
    }

    public function grades(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $grades = AssignmentGrade::query()
                ->where('status', GradeStatus::Approved->value)
                ->whereHas('submission', fn ($q) => $q->where('user_id', $request->user()->id))
                ->with('submission.assignment.lesson:id,title')
                ->orderByDesc('approved_at')
                ->get();

            return response()->json(['data' => $grades->map(fn (AssignmentGrade $g) => [
                'assignment' => $g->submission?->assignment?->title,
                'lesson_id' => $g->submission?->assignment?->lesson_id,
                'score' => $g->score,
                'max_points' => $g->max_points,
                'approved_at' => $g->approved_at?->toIso8601String(),
            ])->all()]);
        });
    }

    private function ownSubmission(Request $request, Assignment $assignment): ?AssignmentSubmission
    {
        return AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $request->user()->id)
            ->with('grade')
            ->first();
    }

    /**
     * The released grade only — never a draft, never the AI-likelihood signal.
     *
     * @return array<string, mixed>|null
     */
    private function releasedGrade(?AssignmentSubmission $submission): ?array
    {
        $grade = $submission?->grade;
        if ($grade === null || $grade->status !== GradeStatus::Approved) {
            return null;
        }

        return [
            'score' => $grade->score,
            'max_points' => $grade->max_points,
            'feedback' => $grade->feedback,
            'criteria' => $grade->criteria,
        ];
    }

    /**
     * @return array{name: string|null, url: string|null}|null
     */
    private function file(AssignmentSubmission $submission): ?array
    {
        if ($submission->file_path === null) {
            return null;
        }

        try {
            $url = Storage::disk('s3')->temporaryUrl($submission->file_path, now()->addHour());
        } catch (Throwable) {
            $url = null;
        }

        return ['name' => $submission->file_name, 'url' => $url];
    }
}

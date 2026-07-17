<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Assignments\ReleaseGrade;
use App\Actions\Assignments\UpdateGrade;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assignments\UpdateGradeRequest;
use App\Models\AssignmentGrade;
use App\Models\AssignmentSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Admin/trainer grading queue (PRD §6.5). Gated by `can:manage-curriculum`. Trainers
 * see the AI draft grade + the advisory AI-likelihood signal, edit, then release.
 */
final class GradingController extends Controller
{
    private const AI_LIKELIHOOD_CAVEAT = 'Rough AI-authorship estimate — low confidence, advisory only. Never share with the student or act on it alone.';

    public function index(Request $request): JsonResponse
    {
        $submissions = AssignmentSubmission::query()
            ->with(['assignment:id,lesson_id,title', 'student:id,name', 'grade'])
            ->when($request->string('status')->toString() === 'ungraded', fn ($q) => $q->whereDoesntHave('grade', fn ($g) => $g->where('status', 'approved')))
            ->when($request->string('status')->toString() === 'released', fn ($q) => $q->whereHas('grade', fn ($g) => $g->where('status', 'approved')))
            ->orderByDesc('submitted_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => $submissions->map(fn (AssignmentSubmission $s) => [
            'submission_id' => $s->id,
            'assignment' => $s->assignment?->title,
            'student' => $s->student?->name,
            'status' => $s->status->value,
            'grade_status' => $s->grade?->status->value,
            'score' => $s->grade?->score,
            'max_points' => $s->grade?->max_points,
            'submitted_at' => $s->submitted_at?->toIso8601String(),
        ])->all()]);
    }

    public function show(AssignmentSubmission $submission): JsonResponse
    {
        $submission->load(['assignment', 'student:id,name', 'grade']);

        return response()->json(['data' => [
            'submission_id' => $submission->id,
            'student' => $submission->student?->name,
            'assignment' => [
                'title' => $submission->assignment?->title,
                'rubric' => $submission->assignment?->rubric ?? [],
                'max_points' => $submission->assignment?->max_points,
            ],
            'body' => $submission->body,
            'link' => $submission->link,
            'file' => $this->file($submission),
            'grade' => $submission->grade === null ? null : [
                'id' => $submission->grade->id,
                'status' => $submission->grade->status->value,
                'score' => $submission->grade->score,
                'max_points' => $submission->grade->max_points,
                'feedback' => $submission->grade->feedback,
                'criteria' => $submission->grade->criteria,
                'ai_likelihood' => $submission->grade->ai_likelihood,          // trainer-only
                'ai_likelihood_caveat' => self::AI_LIKELIHOOD_CAVEAT,
            ],
        ]]);
    }

    public function updateGrade(UpdateGradeRequest $request, AssignmentGrade $grade, UpdateGrade $update): JsonResponse
    {
        $update->handle($grade, (string) $request->input('feedback', ''), $request->input('criteria', []), $request->user());

        return response()->json(['data' => ['score' => $grade->refresh()->score, 'status' => $grade->status->value]]);
    }

    public function release(Request $request, AssignmentGrade $grade, ReleaseGrade $release): JsonResponse
    {
        $release->handle($grade, $request->user());

        return response()->json(['data' => ['status' => $grade->refresh()->status->value]]);
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

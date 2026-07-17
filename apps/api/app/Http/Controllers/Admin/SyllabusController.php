<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Content\ApproveSyllabus;
use App\Enums\SyllabusStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Syllabus\UpdateSyllabusRequest;
use App\Http\Resources\SyllabusResource;
use App\Jobs\GenerateSyllabusJob;
use App\Models\Course;
use App\Models\Syllabus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin/trainer syllabus builder + approval (PRD §6.2). Gated by can:manage-curriculum.
 * Generate with AI → edit → approve; approving renders the branded document and
 * publishes it to the public course page + student dashboard. Editing reverts to draft
 * while the last approved artifact keeps serving.
 */
final class SyllabusController extends Controller
{
    public function index(): JsonResponse
    {
        $courses = Course::query()->orderBy('position')->orderBy('id')->get(['id', 'name', 'slug']);
        $syllabuses = Syllabus::query()->whereIn('course_id', $courses->pluck('id'))->get()->keyBy('course_id');

        return response()->json(['data' => $courses->map(function (Course $course) use ($syllabuses): array {
            $syllabus = $syllabuses->get($course->id);

            return [
                'course_id' => $course->id,
                'name' => $course->name,
                'slug' => $course->slug,
                'has_syllabus' => $syllabus !== null,
                'status' => $syllabus?->status->value,
                'is_stale' => (bool) $syllabus?->is_stale,
                'render_status' => $syllabus?->render_status,
                'version' => $syllabus?->version,
                'downloadable' => (bool) $syllabus?->isDownloadable(),
            ];
        })->all()]);
    }

    public function show(Course $course): JsonResponse
    {
        $syllabus = Syllabus::query()->where('course_id', $course->id)->first();

        return response()->json(['data' => $syllabus === null ? null : new SyllabusResource($syllabus)]);
    }

    public function generate(Request $request, Course $course): JsonResponse
    {
        $syllabus = Syllabus::query()->firstOrCreate(
            ['course_id' => $course->id],
            ['tenant_id' => $course->tenant_id, 'title' => $course->name.' — Syllabus'],
        );

        GenerateSyllabusJob::dispatch($syllabus->id, $request->user()->id);

        return response()->json(['data' => ['status' => 'generating', 'syllabus_id' => $syllabus->id]], 202);
    }

    public function update(UpdateSyllabusRequest $request, Course $course): JsonResponse
    {
        $syllabus = Syllabus::query()->where('course_id', $course->id)->firstOrFail();

        $syllabus->update(['content' => $request->string('content')->toString()]);
        $this->revertToDraft($syllabus);

        return (new SyllabusResource($syllabus->refresh()))->response();
    }

    public function approve(Request $request, Syllabus $syllabus, ApproveSyllabus $approve): JsonResponse
    {
        $approve->handle($syllabus, $request->user());

        return (new SyllabusResource($syllabus->refresh()))->response();
    }

    /** An edit un-approves the working copy; the last rendered artifact keeps serving. */
    private function revertToDraft(Syllabus $syllabus): void
    {
        if ($syllabus->isApproved()) {
            $syllabus->update(['status' => SyllabusStatus::Draft->value]);
        }
    }
}

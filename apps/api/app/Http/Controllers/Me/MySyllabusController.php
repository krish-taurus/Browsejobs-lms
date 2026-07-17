<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Syllabus;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Student dashboard syllabus download (PRD §6.2). Under auth:sanctum without
 * tenant.user, so work wraps in the student's tenant context — and the course id is
 * resolved in-context (not route-model-bound) so tenant scoping applies and another
 * tenant's course 404s. Serves the current approved + rendered syllabus via a
 * short-lived signed URL — no lead form (unlike the public course page).
 */
final class MySyllabusController extends Controller
{
    public function show(Request $request, int $course): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($course): JsonResponse {
            $courseModel = Course::query()->findOrFail($course);
            $syllabus = Syllabus::query()->where('course_id', $courseModel->id)->first();

            return response()->json(['data' => [
                'course' => $courseModel->name,
                'downloadable' => (bool) $syllabus?->isDownloadable(),
                'download' => $this->download($syllabus),
            ]]);
        });
    }

    private function download(?Syllabus $syllabus): ?string
    {
        if ($syllabus === null || ! $syllabus->isDownloadable() || $syllabus->storage_path === null) {
            return null;
        }

        try {
            return Storage::disk((string) config('syllabus.render_disk', 's3'))
                ->temporaryUrl($syllabus->storage_path, now()->addMinutes((int) config('syllabus.download.signed_url_ttl_min', 60)));
        } catch (Throwable) {
            return null;
        }
    }
}

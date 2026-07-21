<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\LessonType;
use App\Enums\VideoSource;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonVideo;
use App\Models\Recording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Attach a video to a `video` lesson: an external embed URL or a Zoom recording
 * from the library. Gated by can:manage-curriculum. (Direct upload is a
 * follow-up — it needs a presigned PUT to the Space.)
 */
final class LessonVideoController extends Controller
{
    public function show(Lesson $lesson): JsonResponse
    {
        $video = LessonVideo::query()->with('recording:id,title')->where('lesson_id', $lesson->id)->first();

        return response()->json(['data' => $video === null ? null : $this->payload($video)]);
    }

    public function upsert(Request $request, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->type === LessonType::Video, 422, 'Lesson is not a video.');

        $validated = $request->validate([
            'source' => ['required', Rule::in([VideoSource::Url->value, VideoSource::Recording->value])],
            'title' => ['nullable', 'string', 'max:190'],
            'url' => ['nullable', 'required_if:source,url', 'url', 'max:1000'],
            'recording_id' => ['nullable', 'required_if:source,recording', 'integer'],
        ]);

        // A recording must belong to this tenant (scoped lookup 404s otherwise).
        if (($validated['source'] ?? null) === VideoSource::Recording->value) {
            Recording::query()->findOrFail((int) $validated['recording_id']);
        }

        $video = LessonVideo::query()->updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'tenant_id' => $lesson->tenant_id,
                'source' => $validated['source'],
                'title' => $validated['title'] ?? null,
                'url' => $validated['source'] === VideoSource::Url->value ? $validated['url'] : null,
                'recording_id' => $validated['source'] === VideoSource::Recording->value ? (int) $validated['recording_id'] : null,
            ],
        );

        return response()->json(['data' => $this->payload($video->fresh()->load('recording:id,title'))]);
    }

    /** Recordings available to attach (this tenant's library). */
    public function recordings(): JsonResponse
    {
        $recordings = Recording::query()
            ->whereNotNull('storage_path')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'title', 'duration_seconds']);

        return response()->json(['data' => $recordings]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LessonVideo $video): array
    {
        return [
            'lesson_id' => $video->lesson_id,
            'source' => $video->source->value,
            'title' => $video->title,
            'url' => $video->url,
            'recording_id' => $video->recording_id,
            'recording_title' => $video->recording?->title,
            'playable_url' => $video->playableUrl(),
        ];
    }
}

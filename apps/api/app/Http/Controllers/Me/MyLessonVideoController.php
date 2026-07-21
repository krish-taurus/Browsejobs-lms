<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\LessonVideo;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The video a student watches for a `video` lesson. Returns a playable URL
 * (external embed as-is, or a signed link for a recording/upload).
 */
final class MyLessonVideoController extends Controller
{
    public function show(Request $request, int $lesson): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($lesson): JsonResponse {
            $video = LessonVideo::query()->with('lesson:id,title', 'recording')->where('lesson_id', $lesson)->firstOrFail();

            return response()->json(['data' => [
                'lesson_id' => $video->lesson_id,
                'title' => $video->title ?? $video->lesson?->title,
                'is_embed' => $video->isEmbed(),
                'url' => $video->playableUrl(),
            ]]);
        });
    }
}

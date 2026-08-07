<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * The daily "simulated live" masterclass: the recorded masterclass streams
 * as-if-live at a fixed time every day, so a lead who arrives on Monday never
 * waits for Saturday's live session. The page gets everything it needs to
 * either count down to today's showing or join it mid-stream at the correct
 * offset (no seeking — it feels live). Platform-global and public: the CRM
 * WhatsApps this page's URL to every interested lead.
 */
final class MasterclassWatchController extends Controller
{
    public function show(?string $slug = null): JsonResponse
    {
        $course = Course::query()->withoutGlobalScope(TenantScope::class)
            ->when(
                $slug !== null,
                fn ($q) => $q->where('slug', $slug),
                fn ($q) => $q->whereNotNull('masterclass_video_url')->orderBy('position')->orderBy('id'),
            )
            ->first();

        if ($course === null) {
            return response()->json(['message' => 'No masterclass found.'], 404);
        }

        $showTime = (string) config('funnel.masterclass_watch_time', '20:00');
        $durationMinutes = (int) config('funnel.masterclass_duration_minutes', 90);

        $todayStart = Carbon::today()->setTimeFromTimeString($showTime);
        $now = Carbon::now();

        if ($now->lt($todayStart)) {
            $status = 'waiting';
            $startsAt = $todayStart;
            $offsetSeconds = 0;
        } elseif ($now->lt($todayStart->copy()->addMinutes($durationMinutes))) {
            $status = 'live';
            $startsAt = $todayStart;
            $offsetSeconds = (int) abs($now->diffInSeconds($todayStart));
        } else {
            $status = 'waiting';
            $startsAt = $todayStart->copy()->addDay();
            $offsetSeconds = 0;
        }

        return response()->json([
            'course' => [
                'name' => $course->name,
                'slug' => $course->slug,
                'tagline' => $course->tagline,
            ],
            'video_url' => $course->masterclass_video_url,
            'status' => $course->masterclass_video_url === null ? 'unavailable' : $status,
            'starts_at' => $startsAt->toIso8601String(),
            'offset_seconds' => $offsetSeconds,
            'duration_minutes' => $durationMinutes,
            'server_now' => $now->toIso8601String(),
        ]);
    }
}

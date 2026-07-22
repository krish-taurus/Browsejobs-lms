<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use Illuminate\Http\JsonResponse;

/**
 * The recordings library grouped by batch (PRD §6.3): every class recording under
 * its batch, with the class name, date/time, and the trainer who took that class —
 * so a specific class's recording is easy to find. Zoom Cloud recordings expose
 * their watch URL + passcode; nothing is imported into our storage.
 */
final class RecordingController extends Controller
{
    public function index(): JsonResponse
    {
        $recordings = Recording::query()
            ->where(fn ($q) => $q->whereNotNull('play_url')->orWhereNotNull('storage_path'))
            ->with([
                'liveSession:id,batch_id,topic_id,title,scheduled_start',
                'liveSession.batch:id,number,course_id,trainer_id',
                'liveSession.batch.course:id,code,name',
                'liveSession.batch.trainer:id,name',
                'liveSession.batch.moduleTrainers.trainer:id,name',
                'liveSession.topic:id,name,module_id',
            ])
            ->orderByDesc('id')
            ->get();

        // Group by batch, newest batch (highest id) first, each with its recordings.
        $groups = $recordings
            ->filter(fn (Recording $r) => $r->liveSession?->batch !== null)
            ->groupBy(fn (Recording $r) => $r->liveSession->batch->id)
            ->map(function ($rows) {
                $batch = $rows->first()->liveSession->batch;

                return [
                    'batch' => [
                        'id' => $batch->id,
                        'number' => $batch->number,
                        'course_code' => $batch->course?->code,
                        'course_name' => $batch->course?->name,
                    ],
                    'recordings' => $rows->map(function (Recording $r) {
                        $session = $r->liveSession;
                        $trainer = $session->batch->trainerForModule($session->topic?->module_id);

                        return [
                            'id' => $r->id,
                            'title' => $r->title,
                            'session_title' => $session->title,
                            'topic' => $session->topic?->name,
                            'recorded_on' => $session->scheduled_start?->toIso8601String(),
                            'duration_seconds' => $r->duration_seconds,
                            'trainer' => $trainer?->name,
                            'watch_url' => $r->play_url,
                            'passcode' => $r->passcode,
                        ];
                    })->values()->all(),
                ];
            })
            ->sortByDesc(fn ($group) => $group['batch']['id'])
            ->values()
            ->all();

        return response()->json(['data' => $groups]);
    }
}

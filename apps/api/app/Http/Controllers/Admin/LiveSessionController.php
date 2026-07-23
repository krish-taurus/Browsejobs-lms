<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\LiveClasses\CancelLiveSession;
use App\Actions\LiveClasses\EnsureZoomMeeting;
use App\Actions\LiveClasses\RescheduleLiveSession;
use App\Actions\LiveClasses\ScheduleBatchSeries;
use App\Actions\LiveClasses\ScheduleLiveSession;
use App\Actions\LiveClasses\StartLiveSession;
use App\Enums\LiveSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelSessionRequest;
use App\Http\Requests\Admin\RescheduleSessionRequest;
use App\Http\Requests\Admin\ScheduleSeriesRequest;
use App\Http\Requests\Admin\ScheduleSessionRequest;
use App\Http\Resources\LiveSessionResource;
use App\Models\Batch;
use App\Models\LiveSession;
use App\Models\Topic;
use App\Support\Time\AppTime;
use App\Support\Zoom\ZoomClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * Admin live-class scheduling (PRD §6.3). Gated by `can:teach-classes`.
 *
 * The scheduling engine (ScheduleLiveSession / RescheduleLiveSession /
 * CancelLiveSession) already handles Zoom, the reminder ladder, the change log, and —
 * for reschedule/cancel — notifying the whole batch. This controller is the missing
 * doorway to it; it adds no business logic of its own.
 *
 * An "extra session" is just another session on the batch: its recording rolls into the
 * same batch library via the Zoom webhook, so no separate temp-batch object exists.
 */
final class LiveSessionController extends Controller
{
    public function index(Batch $batch): JsonResponse
    {
        $sessions = $batch->liveSessions()
            ->with([
                'topic:id,name,module_id',
                'host:id,name',
                'recordings:id,live_session_id,title,status,play_url,passcode',
                'batch.trainer', 'batch.moduleTrainers.trainer',
            ])
            ->orderByDesc('scheduled_start')
            ->get();

        return LiveSessionResource::collection($sessions)->response();
    }

    public function store(ScheduleSessionRequest $request, Batch $batch, ScheduleLiveSession $schedule): JsonResponse
    {
        $topic = null;
        if ($request->filled('topic_id')) {
            // Tenant-scoped: a topic from another tenant resolves to null and is ignored.
            $topic = Topic::query()->find((int) $request->integer('topic_id'));
        }

        $session = $schedule->handle(
            $batch,
            $request->string('title')->toString(),
            AppTime::parse($request->string('scheduled_start')->toString()),
            $request->filled('scheduled_end') ? AppTime::parse($request->string('scheduled_end')->toString()) : null,
            $topic,
            $request->boolean('record', true),
            $request->input('kind') ?: LiveSession::KIND_CLASS,
            $this->resolveHost($request, $batch),
        );

        return (new LiveSessionResource($session->load(['topic:id,name', 'host:id,name'])))->response()->setStatusCode(201);
    }

    /**
     * Generate a batch's whole class calendar in one pass (daily/alt-day cohorts).
     * Delegates each class to the same scheduling engine, so every generated class
     * gets its own Zoom meeting + reminder ladder. Idempotent per slot.
     */
    public function storeSeries(ScheduleSeriesRequest $request, Batch $batch, ScheduleBatchSeries $series): JsonResponse
    {
        $created = $series->handle(
            $batch,
            array_map('intval', $request->array('weekdays')),
            $request->string('time')->toString(),
            (int) $request->integer('duration_minutes'),
            (int) $request->integer('count'),
            AppTime::parse($request->string('start_date')->toString()),
            $request->filled('title_prefix') ? $request->string('title_prefix')->toString() : null,
            $request->boolean('map_topics'),
            $request->boolean('record', true),
            $this->weekdayTimes($request->array('times')),
            $request->input('kind') ?: LiveSession::KIND_CLASS,
            $this->resolveHost($request, $batch),
        );

        return LiveSessionResource::collection(
            collect($created)->each->load(['topic:id,name', 'host:id,name'])
        )->response()->setStatusCode(201);
    }

    /**
     * The explicit host for a mentoring session must be someone actually on the
     * batch — its lead trainer, a module trainer, or an allocated mentor — so a
     * session can never be handed to a stranger (or another tenant's user).
     */
    private function resolveHost(ScheduleSessionRequest|ScheduleSeriesRequest $request, Batch $batch): ?int
    {
        if (! $request->filled('host_user_id')) {
            return null;
        }

        $hostId = (int) $request->integer('host_user_id');
        $batch->loadMissing(['trainer', 'moduleTrainers', 'batchMentors']);

        $staffIds = collect([$batch->trainer_id])
            ->merge($batch->moduleTrainers->pluck('user_id'))
            ->merge($batch->batchMentors->pluck('user_id'))
            ->filter()
            ->map(fn ($id) => (int) $id);

        if (! $staffIds->contains($hostId)) {
            throw ValidationException::withMessages([
                'host_user_id' => 'The host must be a trainer or mentor allocated to this batch.',
            ]);
        }

        return $hostId;
    }

    /**
     * Normalise the request's per-day times into an int-keyed weekday → "HH:MM"
     * map (1 = Mon … 7 = Sun). Non-weekday keys and blank times are dropped.
     *
     * @param  array<int|string, mixed>  $times
     * @return array<int, string>
     */
    private function weekdayTimes(array $times): array
    {
        $out = [];
        foreach ($times as $weekday => $time) {
            $day = (int) $weekday;
            if ($day >= 1 && $day <= 7 && is_string($time) && $time !== '') {
                $out[$day] = $time;
            }
        }

        return $out;
    }

    public function reschedule(RescheduleSessionRequest $request, LiveSession $session, RescheduleLiveSession $reschedule): JsonResponse
    {
        $this->assertChangeable($session);

        $reschedule->handle(
            $session,
            AppTime::parse($request->string('scheduled_start')->toString()),
            $request->filled('scheduled_end') ? AppTime::parse($request->string('scheduled_end')->toString()) : null,
            $request->string('reason')->toString(),
            $request->user(),
        );

        return (new LiveSessionResource($session->fresh(['topic:id,name'])))->response();
    }

    public function cancel(CancelSessionRequest $request, LiveSession $session, CancelLiveSession $cancel): JsonResponse
    {
        $this->assertChangeable($session);

        $cancel->handle($session, $request->string('reason')->toString(), $request->user());

        return (new LiveSessionResource($session->fresh(['topic:id,name'])))->response();
    }

    /**
     * Hand the assigned host (trainer, mentoring-session mentor, or an admin) the Zoom
     * host `start_url` so they can go live in one tap. The route carries no permission
     * middleware — a mentor hosting a weekly mentoring session has no teach-classes —
     * so this endpoint keeps students out itself; who may host is gated + time-windowed
     * by {@see StartLiveSession}, which 422s with the reason when not allowed.
     */
    public function start(LiveSession $session, StartLiveSession $start): JsonResponse
    {
        abort_unless(request()->user()?->user_type === 'staff', 403);

        $url = $start->handle($session, request()->user());

        return response()->json(['data' => ['start_url' => $url]]);
    }

    /**
     * Repair: create the Zoom meeting for a class that doesn't have one yet — e.g.
     * classes scheduled before Zoom was connected. Runs synchronously so the admin
     * gets immediate feedback (and the underlying Zoom error, if any), rather than a
     * silent queued job. Idempotent: a class that already has a meeting is unchanged.
     */
    public function createMeeting(LiveSession $session, EnsureZoomMeeting $ensure, ZoomClient $zoom): JsonResponse
    {
        $this->assertChangeable($session);

        if ($session->zoom_meeting_id !== null) {
            return (new LiveSessionResource($session->fresh(['topic:id,name'])))->response();
        }

        try {
            $ensure->handle($session, $zoom);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'zoom' => 'Zoom could not create the meeting: '.$this->zoomError($e)
                    .' Check that a Zoom license is connected, the app has meeting:write scope, and ZOOM_DEFAULT_HOST_ID is set to your account owner\'s email.',
            ]);
        }

        return (new LiveSessionResource($session->fresh(['topic:id,name'])))->response();
    }

    /** A short, human-readable slice of a Zoom API/client error for the admin UI. */
    private function zoomError(Throwable $e): string
    {
        $message = trim($e->getMessage());

        return $message === '' ? class_basename($e) : mb_strimwidth($message, 0, 300, '…');
    }

    /**
     * A cancelled or ended class cannot be moved or cancelled again — notifying a batch
     * about a change to a class that already happened (or was already called off) is
     * noise the students would not trust.
     */
    private function assertChangeable(LiveSession $session): void
    {
        if (in_array($session->status, [LiveSessionStatus::Cancelled, LiveSessionStatus::Ended], true)) {
            throw new ConflictHttpException('This class has already ended or been cancelled.');
        }
    }
}

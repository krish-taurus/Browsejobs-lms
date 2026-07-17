<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\LiveClasses\CancelLiveSession;
use App\Actions\LiveClasses\RescheduleLiveSession;
use App\Actions\LiveClasses\ScheduleLiveSession;
use App\Enums\LiveSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelSessionRequest;
use App\Http\Requests\Admin\RescheduleSessionRequest;
use App\Http\Requests\Admin\ScheduleSessionRequest;
use App\Http\Resources\LiveSessionResource;
use App\Models\Batch;
use App\Models\LiveSession;
use App\Models\Topic;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
            ->with(['topic:id,name', 'recordings:id,live_session_id,title,status'])
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
            CarbonImmutable::parse($request->string('scheduled_start')->toString()),
            $request->filled('scheduled_end') ? CarbonImmutable::parse($request->string('scheduled_end')->toString()) : null,
            $topic,
        );

        return (new LiveSessionResource($session->load('topic:id,name')))->response()->setStatusCode(201);
    }

    public function reschedule(RescheduleSessionRequest $request, LiveSession $session, RescheduleLiveSession $reschedule): JsonResponse
    {
        $this->assertChangeable($session);

        $reschedule->handle(
            $session,
            CarbonImmutable::parse($request->string('scheduled_start')->toString()),
            $request->filled('scheduled_end') ? CarbonImmutable::parse($request->string('scheduled_end')->toString()) : null,
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

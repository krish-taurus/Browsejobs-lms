<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Enums\BatchMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\BatchMember;
use App\Models\LiveSession;
use App\Models\Recording;
use App\Support\Fees\FeeGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The student's class recordings (PRD §6.3/§6.8). Lists stored recordings for the
 * student's batches; the download endpoint gates on active membership and the fee gate
 * (the Recordings tab locks with the soft-block), then hands out a short-lived signed URL.
 *
 * Unlike live classes, self-paced enrolments DO include recordings, so there is no
 * self-paced exclusion here.
 */
final class MyRecordingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $batchIds = $this->batchIds($request->user()->id);

            // Every class that has already happened, not only the ones that
            // produced a recording. Listing just the recordings left a student
            // unable to tell "no classes yet" apart from "this class was never
            // recorded", which is exactly the question they open this page with.
            $sessions = LiveSession::query()
                ->whereIn('batch_id', $batchIds)
                ->where('status', 'ended')
                ->with(['batch:id,number,course_id', 'batch.course:id,code,name'])
                ->orderByDesc('scheduled_start')
                ->get();

            $recordings = Recording::query()
                ->where('status', 'stored')
                ->whereIn('live_session_id', $sessions->pluck('id'))
                ->get()
                ->keyBy('live_session_id');

            return response()->json([
                'data' => $sessions->map(function (LiveSession $session) use ($recordings) {
                    $recording = $recordings->get($session->id);
                    $batch = $session->batch;

                    return [
                        // null when the class produced no recording; the page keys
                        // off session_id so a class without one still has a row.
                        'id' => $recording?->id,
                        'session_id' => $session->id,
                        'has_recording' => $recording !== null,
                        'title' => $recording?->title ?? $session->title,
                        'duration_seconds' => $recording?->duration_seconds,
                        'class' => $session->title,
                        'recorded_on' => $session->scheduled_start?->toIso8601String(),
                        'batch_number' => $batch?->number,
                        'course_code' => $batch?->course?->code,
                        'course_name' => $batch?->course?->name,
                    ];
                })->all(),
            ]);
        });
    }

    /**
     * Hands the student the Zoom Cloud watch URL (+ passcode) for a recording, gated by
     * active membership and the fee gate. Legacy imported recordings fall back to a
     * short-lived signed storage URL.
     */
    public function download(Request $request, int $recording, FeeGate $feeGate): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $recording, $feeGate): JsonResponse {
            $model = Recording::query()->with('liveSession.batch')->find($recording);
            $batch = $model?->liveSession?->batch;

            if ($model === null || $batch === null) {
                throw new NotFoundHttpException;
            }

            $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
            $member = BatchMember::query()
                ->where('batch_id', $batch->id)
                ->where('user_id', $request->user()->id)
                ->whereIn('status', $occupying)
                ->first();

            if ($member === null) {
                throw new NotFoundHttpException; // not their recording
            }

            // Recordings lock with the fee soft-block, same as live access.
            abort_unless($feeGate->allowsLiveAccess($request->user(), $batch), 403, 'Access is locked until your fee dues are cleared.');

            if (! $model->isWatchable()) {
                throw new NotFoundHttpException;
            }

            // A self-hosted copy wins: it plays INSIDE the portal (no Zoom
            // page, no passcode). Served through the ranged media route —
            // browsers need HTTP Range support to play MP4s. Zoom's play page
            // and S3 are the fallbacks.
            $localUrl = null;
            if ($model->storage_path !== null && Storage::disk('public')->exists($model->storage_path)) {
                // ?v= busts the browser cache whenever this row changes — the
                // path alone never does, so a replaced file (a corrected
                // recording swapped in for a stray earlier one, say) used to
                // keep serving whoever already opened this class their
                // browser's stale cached copy of the old bytes forever.
                $localUrl = rtrim((string) config('app.url'), '/').'/media/'.$model->storage_path
                    .'?v='.$model->updated_at?->timestamp;
            }

            // Falling back to Zoom's own page, carry the passcode IN the link
            // (`?pwd=`) rather than handing the student a 100-character code to
            // copy and paste. A student who has to transcribe a passcode to
            // watch a class they already paid for simply does not watch it.
            $zoomUrl = $model->play_url;
            if ($localUrl === null && $zoomUrl !== null && (string) $model->passcode !== '') {
                $zoomUrl .= (str_contains($zoomUrl, '?') ? '&' : '?').'pwd='.rawurlencode((string) $model->passcode);
            }

            $watchUrl = $localUrl ?? $zoomUrl ?? ($model->storage_path !== null ? $this->signedUrl($model->storage_path) : null);

            return response()->json(['data' => [
                'watch_url' => $watchUrl,
                // Never surfaced to the student: it is either unnecessary (local
                // copy) or already embedded in the link above.
                'passcode' => null,
                'embedded' => $localUrl !== null,
            ]]);
        });
    }

    /** A short-lived signed URL, or null if the disk can't mint one (e.g. local/test). */
    private function signedUrl(string $path): ?string
    {
        try {
            return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(30));
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<int> */
    private function batchIds(int $userId): array
    {
        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        return BatchMember::query()
            ->where('user_id', $userId)
            ->whereIn('status', $occupying)
            ->pluck('batch_id')
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Enums\BatchMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\BatchMember;
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

            $recordings = Recording::query()
                ->where('status', 'stored')
                ->whereHas('liveSession', fn ($q) => $q->whereIn('batch_id', $batchIds))
                ->with('liveSession:id,title,scheduled_start,batch_id')
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'data' => $recordings->map(fn (Recording $r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'duration_seconds' => $r->duration_seconds,
                    'class' => $r->liveSession?->title,
                    'recorded_on' => $r->liveSession?->scheduled_start?->toIso8601String(),
                ])->all(),
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

            $watchUrl = $model->play_url ?? ($model->storage_path !== null ? $this->signedUrl($model->storage_path) : null);

            return response()->json(['data' => [
                'watch_url' => $watchUrl,
                'passcode' => $model->passcode,
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

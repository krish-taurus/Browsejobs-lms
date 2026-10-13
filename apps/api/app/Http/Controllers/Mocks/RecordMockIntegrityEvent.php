<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mocks;

use App\Http\Controllers\Controller;
use App\Models\MockIntegrityEvent;
use App\Models\MockInterview;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The interview room reports what it noticed — a tab switch, a paste, the
 * face leaving the camera, a possible second person — with a still of the
 * candidate at that moment, plus a few random check-in stills. Kept for the
 * employer's report; the candidate was told in the room's rules that this
 * happens. Stills are private: only the report (and the employer's own
 * candidate page) ever shows them.
 *
 * Only for the candidate's own interview while it is running, severity is
 * decided here rather than trusted from the browser, and an interview can
 * record at most MAX_EVENTS events and MAX_IMAGES stills.
 */
final class RecordMockIntegrityEvent extends Controller
{
    public function __invoke(Request $request, int $mock): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', Rule::in(array_keys(MockIntegrityEvent::KINDS))],
            'elapsed' => ['nullable', 'integer', 'min:0', 'max:86400'],
            // A downscaled JPEG from the room's own camera tile.
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg', 'max:400'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $mock, $data): JsonResponse {
            $interview = MockInterview::query()->where('user_id', $request->user()->id)->findOrFail($mock);
            if ($interview->status !== MockInterview::STATUS_IN_PROGRESS) {
                return response()->json(['ok' => false, 'reason' => 'not_running'], 409);
            }

            $recorded = MockIntegrityEvent::query()->where('mock_interview_id', $interview->id);
            if ((clone $recorded)->count() >= MockIntegrityEvent::MAX_EVENTS) {
                return response()->json(['ok' => false, 'reason' => 'limit']);
            }

            $event = MockIntegrityEvent::query()->create([
                'tenant_id' => $interview->tenant_id,
                'mock_interview_id' => $interview->id,
                'kind' => $data['kind'],
                'severity' => MockIntegrityEvent::KINDS[$data['kind']],
                'elapsed_seconds' => $data['elapsed'] ?? null,
                'occurred_at' => now(),
            ]);

            $image = $request->file('image');
            if ($image !== null && (clone $recorded)->whereNotNull('image_path')->count() < MockIntegrityEvent::MAX_IMAGES) {
                $path = "mock-integrity/{$interview->tenant_id}/{$interview->id}/{$event->id}.jpg";
                Storage::disk('s3')->put($path, (string) file_get_contents($image->getRealPath()), 'private');
                $event->update(['image_path' => $path]);
            }

            return response()->json(['ok' => true, 'id' => $event->id]);
        });
    }
}

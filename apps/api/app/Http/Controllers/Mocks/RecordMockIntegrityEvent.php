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
            // Numbers behind a lip-sync finding, as JSON. Only the keys a kind may carry are kept.
            'detail' => ['nullable', 'string', 'max:400'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $mock, $data): JsonResponse {
            $interview = MockInterview::query()->where('user_id', $request->user()->id)->findOrFail($mock);
            if ($interview->status !== MockInterview::STATUS_IN_PROGRESS || $interview->ended_at !== null) {
                return response()->json(['ok' => false, 'reason' => 'not_running'], 409);
            }

            $recorded = MockIntegrityEvent::query()->where('mock_interview_id', $interview->id);
            if ((clone $recorded)->count() >= MockIntegrityEvent::MAX_EVENTS) {
                return response()->json(['ok' => false, 'reason' => 'limit']);
            }

            $kind = (string) $data['kind'];
            $detail = $this->cleanDetail($kind, $data['detail'] ?? null);

            // The browser can raise lip-sync moments, not the server-side voice findings.
            if (in_array($kind, ['second_voice', 'voice_check'], true)) {
                return response()->json(['ok' => false, 'reason' => 'not_allowed'], 422);
            }
            if (str_starts_with($kind, 'lip_') && $kind !== 'lip_sync_summary'
                && (clone $recorded)->where('kind', $kind)->count() >= 4) {
                return response()->json(['ok' => false, 'reason' => 'limit']);
            }
            // One summary per interview: a second one replaces the first.
            if ($kind === 'lip_sync_summary') {
                (clone $recorded)->where('kind', $kind)->delete();
            }

            $event = MockIntegrityEvent::query()->create([
                'tenant_id' => $interview->tenant_id,
                'mock_interview_id' => $interview->id,
                'kind' => $data['kind'],
                'severity' => MockIntegrityEvent::KINDS[$data['kind']],
                'elapsed_seconds' => $data['elapsed'] ?? null,
                'occurred_at' => now(),
                'detail' => $detail,
            ]);

            $image = $request->file('image');
            if ($image !== null && ! in_array($kind, MockIntegrityEvent::MEASUREMENTS, true) && (clone $recorded)->whereNotNull('image_path')->count() < MockIntegrityEvent::MAX_IMAGES) {
                $path = "mock-integrity/{$interview->tenant_id}/{$interview->id}/{$event->id}.jpg";
                Storage::disk('s3')->put($path, (string) file_get_contents($image->getRealPath()), 'private');
                $event->update(['image_path' => $path]);
            }

            return response()->json(['ok' => true, 'id' => $event->id]);
        });
    }

    /**
     * Only the numbers this kind may carry, each clamped; anything else is dropped.
     *
     * @return array<string, int|null>|null
     */
    private function cleanDetail(string $kind, ?string $raw): ?array
    {
        $allowed = MockIntegrityEvent::DETAIL_KEYS[$kind] ?? null;
        if ($allowed === null || $raw === null || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        $out = [];
        foreach ($allowed as $key) {
            $value = $decoded[$key] ?? null;
            $out[$key] = is_numeric($value) ? max(0, min(1_000_000, (int) round((float) $value))) : null;
        }

        return $out;
    }
}

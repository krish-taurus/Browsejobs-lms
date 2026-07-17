<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Mocks\CompleteVoiceMock;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives verified voice-provider webhooks (secret checked by middleware).
 * Normalises Vapi's end-of-call report into the CompleteVoiceMock pipeline;
 * unknown message types are acknowledged and ignored.
 */
final class VoiceWebhookController extends Controller
{
    public function __invoke(Request $request, CompleteVoiceMock $complete): JsonResponse
    {
        $message = (array) $request->input('message', []);
        $type = (string) ($message['type'] ?? '');

        if (! in_array($type, ['end-of-call-report', 'call.ended', 'call.failed'], true)) {
            return response()->json(['ok' => true]);
        }

        $call = (array) ($message['call'] ?? []);
        $sessionId = (string) ($call['id'] ?? $message['callId'] ?? '');
        if ($sessionId === '') {
            return response()->json(['ok' => true]);
        }

        $transcript = collect((array) ($message['artifact']['messages'] ?? $message['transcript'] ?? []))
            ->filter(fn ($m) => is_array($m))
            ->map(fn (array $m) => [
                'role' => (string) ($m['role'] ?? ''),
                'text' => (string) ($m['message'] ?? $m['text'] ?? $m['content'] ?? ''),
            ])
            ->reject(fn (array $m) => $m['role'] === 'system' || trim($m['text']) === '')
            ->values()
            ->all();

        // Vapi reports cost in USD; the ledger stores micros of the tenant
        // currency unit — store micro-dollars and let reporting convert.
        $costMicros = (int) round(((float) ($message['cost'] ?? 0)) * 1_000_000);
        $duration = (int) round((float) ($message['durationSeconds'] ?? $message['duration'] ?? 0));

        $complete->handle(
            $sessionId,
            $transcript,
            $duration,
            $costMicros,
            failed: $type === 'call.failed',
        );

        return response()->json(['ok' => true]);
    }
}

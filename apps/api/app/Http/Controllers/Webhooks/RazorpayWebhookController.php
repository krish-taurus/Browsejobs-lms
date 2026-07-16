<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Payments\ReconcilePayment;
use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives signature-verified Razorpay webhooks (PRD §6.8). Logs every delivery,
 * then reconciles it against the ledger. Idempotent handling lives in
 * ReconcilePayment; unmatched events are logged and ignored.
 */
final class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, ReconcilePayment $reconcile): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = (array) $request->all();

        WebhookLog::query()->create([
            'provider' => 'razorpay',
            'event' => (string) ($payload['event'] ?? ''),
            'event_id' => $request->header('x-razorpay-event-id'),
            'signature_valid' => true, // middleware rejected invalid signatures.
            'payload' => $payload,
            'processed_at' => now(),
        ]);

        $reconcile->handle($payload);

        return response()->json(['ok' => true]);
    }
}

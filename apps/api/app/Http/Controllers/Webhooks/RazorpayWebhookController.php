<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Payments\ReconcilePayment;
use App\Actions\Store\ReconcilePurchase;
use App\Actions\Store\ReconcileSubscription;
use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives signature-verified Razorpay webhooks (PRD §6.8/§6.17). Logs every
 * delivery, then fans out to the fee, store-purchase, and subscription reconcilers
 * — each domain-scoped and idempotent; unmatched events are logged and ignored.
 */
final class RazorpayWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        ReconcilePayment $reconcilePayment,
        ReconcilePurchase $reconcilePurchase,
        ReconcileSubscription $reconcileSubscription,
    ): JsonResponse {
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

        // Fee instalment orders — the original path (unchanged).
        if ($reconcilePayment->handle($payload) === null) {
            // Not a fee order: try store purchases + Career+ subscriptions.
            $reconcilePurchase->handle($payload);
            $reconcileSubscription->handle($payload);
        }

        return response()->json(['ok' => true]);
    }
}

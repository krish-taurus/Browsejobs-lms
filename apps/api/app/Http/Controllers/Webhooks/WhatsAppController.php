<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Messaging\RecordInboundMessage;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WebhookLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * WhatsApp Cloud API webhook (PRD §6.9 two-way). GET = the subscription
 * handshake; POST (signature verified by `whatsapp.signed`) delivers inbound
 * messages → the CRM timeline + reply signal, and delivery/read statuses →
 * the message log. Single-WABA tenant resolution via config.
 */
final class WhatsAppController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode', (string) $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', (string) $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', (string) $request->query('hub.challenge', ''));
        $expected = (string) config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        abort(403, 'WhatsApp verification failed.');
    }

    public function store(Request $request, RecordInboundMessage $inbound): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = (array) $request->all();

        WebhookLog::query()->create([
            'provider' => 'whatsapp',
            'event' => 'inbound',
            'signature_valid' => true,
            'payload' => $payload,
            'processed_at' => now(),
        ]);

        $tenant = $this->resolveTenant();
        if ($tenant === null) {
            return response()->json(['ok' => true, 'skipped' => 'no_tenant']);
        }

        $received = 0;
        app(TenantContext::class)->run($tenant, function () use ($payload, $inbound, $tenant, &$received): void {
            foreach ((array) ($payload['entry'] ?? []) as $entry) {
                foreach ((array) ($entry['changes'] ?? []) as $change) {
                    $value = (array) ($change['value'] ?? []);

                    foreach ((array) ($value['messages'] ?? []) as $msg) {
                        $from = (string) ($msg['from'] ?? '');
                        $body = (string) ($msg['text']['body'] ?? '');
                        if ($from === '' || $body === '') {
                            continue;
                        }
                        $inbound->handle($tenant->id, $from, $body, $msg['id'] ?? null);
                        $received++;
                    }

                    foreach ((array) ($value['statuses'] ?? []) as $status) {
                        $this->applyStatus($status);
                    }
                }
            }
        });

        return response()->json(['ok' => true, 'received' => $received]);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function applyStatus(array $status): void
    {
        $id = $status['id'] ?? null;
        $state = $status['status'] ?? null;
        if (! is_string($id) || ! in_array($state, ['delivered', 'read', 'failed'], true)) {
            return;
        }

        $message = Message::query()->where('provider_message_id', $id)->first();
        if ($message === null) {
            return;
        }

        match ($state) {
            'delivered' => $message->update(['status' => MessageStatus::Delivered->value, 'delivered_at' => now()]),
            'read' => $message->update(['status' => MessageStatus::Read->value, 'read_at' => now()]),
            'failed' => $message->update(['status' => MessageStatus::Failed->value, 'failed_reason' => 'provider_failed']),
            default => null,
        };
    }

    private function resolveTenant(): ?Tenant
    {
        $slug = (string) config('services.whatsapp.tenant_slug');
        if ($slug === '') {
            $slug = (string) config('tenancy.default_slug');
        }
        if ($slug === '') {
            return null;
        }

        return Tenant::query()->where('slug', $slug)->where('status', 'active')->first();
    }
}

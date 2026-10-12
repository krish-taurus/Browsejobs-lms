<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmployerWhatsappMessage;
use App\Models\Tenant;
use App\Support\Employers\HiringChatAgent;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The hiring chat agent's turn for one inbound WhatsApp message (AI calls
 * and the reply send happen here, never in the webhook). At-most-once: the
 * message is claimed atomically, so a retry never sends a second reply.
 */
final class ProcessEmployerWhatsAppMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly int $messageId) {}

    public function handle(HiringChatAgent $agent): void
    {
        $claimed = EmployerWhatsappMessage::withoutGlobalScopes()
            ->whereKey($this->messageId)
            ->where('status', 'received')
            ->update(['status' => 'processing']);
        if ($claimed !== 1) {
            return;
        }

        $message = EmployerWhatsappMessage::withoutGlobalScopes()->find($this->messageId);
        $tenant = $message !== null ? Tenant::query()->find($message->tenant_id) : null;
        if ($message === null || $tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($agent, $message): void {
            $agent->handle($message);
            $message->update(['status' => 'processed']);
        });
    }
}

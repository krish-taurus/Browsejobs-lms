<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmployerJob;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWhatsappMessage;
use App\Models\Tenant;
use App\Support\Employers\ShortlistReport;
use App\Support\Tenancy\TenantContext;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * After the shortlist agent runs on a job posted from WhatsApp: send the
 * shortlist back to that chat. Free text, so only inside WhatsApp's 24-hour
 * window (the client just replied YES, so it is open); outside it the client
 * can send SHORTLIST to get it.
 */
final class SendShortlistReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $employerJobId) {}

    public function handle(WhatsAppClient $whatsapp, ShortlistReport $report): void
    {
        $job = EmployerJob::withoutGlobalScopes()->find($this->employerJobId);
        $link = $job?->whatsapp_link_id !== null ? EmployerWhatsappLink::withoutGlobalScopes()->find($job->whatsapp_link_id) : null;
        $tenant = $job !== null ? Tenant::query()->find($job->tenant_id) : null;
        if ($job === null || $link === null || $tenant === null) {
            return;
        }
        if ($link->last_inbound_at === null || $link->last_inbound_at->lt(now()->subHours(23))) {
            return; // window closed — they can ask with SHORTLIST
        }

        app(TenantContext::class)->run($tenant, function () use ($job, $link, $whatsapp, $report): void {
            $body = $report->text($job);
            $message = EmployerWhatsappMessage::query()->create([
                'tenant_id' => $job->tenant_id,
                'employer_whatsapp_link_id' => $link->id,
                'phone' => $link->phone,
                'direction' => 'out',
                'body' => $body,
                'status' => 'sent',
            ]);

            try {
                $message->update(['wamid' => $whatsapp->sendMessage($link->phone, $body)]);
            } catch (Throwable $e) {
                report($e);
                $message->update(['status' => 'failed']);
            }
        });
    }
}

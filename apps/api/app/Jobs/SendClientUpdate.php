<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmployerJob;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWhatsappMessage;
use App\Models\Tenant;
use App\Support\Employers\InterviewTemplates;
use App\Support\Tenancy\TenantContext;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * One line to the client on WhatsApp about a stage of their hiring: a
 * candidate booked, declined, finished a round (score, pass or not), missed
 * their slot. Sent to the chat the JD was posted from, else the workspace's
 * most recent hiring chat. Inside WhatsApp's 24-hour window it goes as a
 * message; outside it, as the approved update template ("… reply REPORT").
 * A BrowseJobs student's phone or email is never in it.
 */
final class SendClientUpdate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $employerJobId, public readonly string $text) {}

    public function handle(WhatsAppClient $whatsapp, InterviewTemplates $templates): void
    {
        $job = EmployerJob::withoutGlobalScopes()->find($this->employerJobId);
        $tenant = $job !== null ? Tenant::query()->find($job->tenant_id) : null;
        if ($job === null || $tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($job, $whatsapp, $templates): void {
            $link = ($job->whatsapp_link_id !== null ? EmployerWhatsappLink::query()->find($job->whatsapp_link_id) : null)
                ?? EmployerWhatsappLink::query()->where('employer_workspace_id', $job->employer_workspace_id)->latest('last_inbound_at')->first();
            if ($link === null) {
                return; // No hiring chat: the client sees it on the pipeline.
            }

            $line = Str::limit(trim((string) preg_replace('/\s+/', ' ', $this->text)), 700);
            $message = EmployerWhatsappMessage::query()->create([
                'tenant_id' => $job->tenant_id,
                'employer_whatsapp_link_id' => $link->id,
                'phone' => $link->phone,
                'direction' => 'out',
                'body' => "📣 *{$job->title}* · {$line}",
                'status' => 'sent',
            ]);

            try {
                if ($link->last_inbound_at !== null && $link->last_inbound_at->gt(now()->subHours(23))) {
                    $wamid = $whatsapp->sendMessage($link->phone, $message->body);
                } elseif ($templates->approved(InterviewTemplates::UPDATE)) {
                    $contact = trim(explode(' ', trim((string) $link->user?->name))[0] ?? '') ?: 'there';
                    $wamid = $whatsapp->sendMessage($link->phone, '', InterviewTemplates::UPDATE, [Str::limit($contact, 30, ''), (string) $job->title, $line]);
                } else {
                    $message->update(['status' => 'failed']);

                    return; // Outside the window and no approved template: REPORT shows it next time.
                }
                $message->update(['wamid' => $wamid]);
            } catch (Throwable $e) {
                report($e);
                $message->update(['status' => 'failed']);
            }
        });
    }
}

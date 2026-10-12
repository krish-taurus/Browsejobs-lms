<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\EmployerInterviewStatus;
use App\Models\EmployerInterview;
use App\Models\EmployerJobApplication;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Hiring step 7: a candidate cleared the AI interview rounds. Tell the client
 * on WhatsApp — the chat the JD was posted from, else the workspace's most
 * recent hiring chat — with each round's score, the AI's summary, and the
 * link to the full report and recordings. Inside WhatsApp's 24-hour window
 * that is the full message; outside it, the approved result template
 * ("… reply REPORT"), and REPORT brings up the shortlist report, which lists
 * everyone who cleared.
 */
final class SendHiringResult implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $applicationId) {}

    public function handle(WhatsAppClient $whatsapp, InterviewTemplates $templates): void
    {
        $application = EmployerJobApplication::withoutGlobalScopes()->find($this->applicationId);
        $tenant = $application !== null ? Tenant::query()->find($application->tenant_id) : null;
        if ($application === null || $tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($application, $whatsapp, $templates): void {
            $job = $application->job()->with('workspace:id,name')->first();
            $link = $job === null ? null : ($job->whatsapp_link_id !== null ? EmployerWhatsappLink::query()->find($job->whatsapp_link_id) : null)
                ?? EmployerWhatsappLink::query()->where('employer_workspace_id', $job->employer_workspace_id)->latest('last_inbound_at')->first();
            if ($job === null || $link === null) {
                return; // No hiring chat: the client sees the result on the pipeline.
            }

            $rounds = EmployerInterview::query()
                ->where('employer_job_application_id', $application->id)
                ->where('status', EmployerInterviewStatus::Graded->value)
                ->orderBy('id')
                ->get()
                ->keyBy('round');
            $name = (string) ($application->candidate()->withoutGlobalScopes()->value('name') ?? 'A candidate');

            $windowOpen = $link->last_inbound_at !== null && $link->last_inbound_at->gt(now()->subHours(23));
            $body = self::text($job->title, $name, $rounds, $job->id, $application->id);

            $message = EmployerWhatsappMessage::query()->create([
                'tenant_id' => $application->tenant_id,
                'employer_whatsapp_link_id' => $link->id,
                'phone' => $link->phone,
                'direction' => 'out',
                'body' => $body,
                'status' => 'sent',
            ]);

            try {
                if ($windowOpen) {
                    $wamid = $whatsapp->sendMessage($link->phone, $body);
                } elseif ($templates->approved(InterviewTemplates::RESULT)) {
                    $contact = trim(explode(' ', trim((string) $link->user?->name))[0] ?? '') ?: 'there';
                    $wamid = $whatsapp->sendMessage($link->phone, '', InterviewTemplates::RESULT, [
                        Str::limit($contact, 30, ''),
                        Str::limit($name, 60, ''),
                        (string) $job->title,
                        (string) ($rounds->get('l1')?->overall_score ?? '—'),
                        (string) ($rounds->get('l2')?->overall_score ?? '—'),
                    ]);
                } else {
                    $message->update(['status' => 'failed']);

                    return; // Outside the window with no approved template: REPORT shows it next time they write.
                }
                $message->update(['wamid' => $wamid]);
            } catch (Throwable $e) {
                report($e);
                $message->update(['status' => 'failed']);
            }
        });
    }

    /** @param  Collection<string, EmployerInterview>  $rounds */
    public static function text(string $role, string $name, Collection $rounds, int $jobId, int $applicationId): string
    {
        $scores = $rounds->map(fn (EmployerInterview $i, string $round) => strtoupper($round).' '.$i->overall_score.'/100')->implode(' · ');
        $summary = $rounds->last()?->grading_summary;
        $url = rtrim((string) config('app.frontend_url'), '/')."/employer/jobs/{$jobId}/candidates/{$applicationId}";

        return "🏆 *{$name}* cleared the AI interviews for *{$role}*.\n\n{$scores}"
            .($summary ? "\n\n_".Str::limit(trim($summary), 400).'_' : '')
            ."\n\nFull report, answers and interview recordings:\n{$url}";
    }
}

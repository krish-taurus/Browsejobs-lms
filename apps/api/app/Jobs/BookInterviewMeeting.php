<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmployerWhatsappMessage;
use App\Models\InterviewInvite;
use App\Models\Tenant;
use App\Support\Employers\CandidateChatAgent;
use App\Support\Interviews\Bots\MeetingBotClient;
use App\Support\Tenancy\TenantContext;
use App\Support\WhatsApp\WhatsAppClient;
use App\Support\Zoom\ZoomClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * A candidate just picked their L1/L2 slot on WhatsApp: make the Zoom
 * meeting for it now (or move it, when they picked another slot), book the
 * meeting bot that runs the AI interviewer to join at that time, and send
 * them the Zoom link in the chat (free text: they have just written to us).
 *
 * If Zoom or the bot cannot be set up, nothing half-made is left behind: the
 * meeting is removed and the interview falls back to the BrowseJobs room
 * link on the day, and the candidate is told so.
 */
final class BookInterviewMeeting implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /** Long enough for the candidate's answers and some slack. */
    private const MEETING_MINUTES = 45;

    public function __construct(public readonly int $inviteId) {}

    public function handle(ZoomClient $zoom, MeetingBotClient $bots, WhatsAppClient $whatsapp): void
    {
        $invite = InterviewInvite::withoutGlobalScopes()->find($this->inviteId);
        $tenant = $invite !== null ? Tenant::query()->find($invite->tenant_id) : null;
        if ($invite === null || $tenant === null || $invite->status !== InterviewInvite::SCHEDULED || $invite->scheduled_at === null || ! $bots->enabled()) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($invite, $zoom, $bots, $whatsapp): void {
            $job = $invite->job()->with('workspace:id,name')->first();
            $topic = Str::limit(($job?->title ?? 'Interview').' — '.$invite->roundLabel().' AI interview · '.($job?->workspace?->name ?? 'BrowseJobs'), 190, '');

            try {
                if ($invite->zoom_meeting_id !== null) {
                    $zoom->updateMeeting($invite->zoom_meeting_id, $invite->scheduled_at, self::MEETING_MINUTES);
                } else {
                    $host = (string) config('services.zoom.default_host_id');
                    $meeting = $zoom->createMeeting($topic, $invite->scheduled_at, self::MEETING_MINUTES, $host !== '' ? $host : null, false, joinBeforeHost: true);
                    $invite->update(['zoom_meeting_id' => $meeting->id, 'zoom_join_url' => $meeting->joinUrl]);
                }

                if ($invite->bot_id !== null) {
                    rescue(fn () => $bots->cancel($invite->bot_id), report: false);
                }

                $code = $invite->bot_code ?? Str::random(48);
                $page = rtrim((string) config('app.frontend_url'), '/').'/interview-bot/'.$code;
                $botId = $bots->schedule((string) $invite->zoom_join_url, $invite->scheduled_at->copy()->subMinute(), $page, [
                    'interview_invite_id' => (string) $invite->id,
                    'round' => $invite->round,
                ]);

                $invite->update(['bot_code' => $code, 'bot_id' => $botId, 'bot_status' => 'scheduled', 'error' => null]);
                $this->say($whatsapp, $invite, CandidateChatAgent::zoomMessage($invite->fresh()));
            } catch (Throwable $e) {
                report($e);
                if ($invite->zoom_meeting_id !== null) {
                    rescue(fn () => $zoom->deleteMeeting($invite->zoom_meeting_id), report: false);
                }
                $invite->update([
                    'zoom_meeting_id' => null,
                    'zoom_join_url' => null,
                    'bot_id' => null,
                    'bot_status' => 'failed',
                    'error' => Str::limit('Zoom interview not set up: '.$e->getMessage(), 240, ''),
                ]);
                $this->say($whatsapp, $invite, "Booked for *{$invite->slotLabel()}*. We could not set up the Zoom call just now, so that morning you will get a WhatsApp link to take your AI interview on your phone or laptop instead.");
            }
        });
    }

    private function say(WhatsAppClient $whatsapp, InterviewInvite $invite, string $body): void
    {
        $message = EmployerWhatsappMessage::query()->create([
            'tenant_id' => $invite->tenant_id,
            'interview_invite_id' => $invite->id,
            'phone' => $invite->phone,
            'direction' => 'out',
            'body' => $body,
            'status' => 'sent',
        ]);

        try {
            $message->update(['wamid' => $whatsapp->sendMessage($invite->phone, $body)]);
        } catch (Throwable $e) {
            report($e);
            $message->update(['status' => 'failed']);
        }
    }
}

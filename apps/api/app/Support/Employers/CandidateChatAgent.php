<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Actions\Employers\OpenInterviewNow;
use App\Actions\Employers\SendInterviewInvites;
use App\Jobs\BookInterviewMeeting;
use App\Jobs\CancelInterviewMeeting;
use App\Jobs\SendClientUpdate;
use App\Models\EmployerWhatsappMessage;
use App\Models\InterviewInvite;
use App\Support\Audit\AuditLogger;
use App\Support\Interviews\Bots\MeetingBotClient;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The message agent's side of a candidate's WhatsApp chat (hiring step 5).
 *
 *   "1" / "2" / "3" / "Tuesday" / "15"  → book that slot (day + the JD's interview time)
 *   "tomorrow" / "kal"                   → book tomorrow's slot, when it is one of the three
 *   "now" / "today" / "abhi"             → take the interview straight away (OpenInterviewNow)
 *   "not interested" / "no"              → decline, politely
 *   anything once the round is open      → the link again
 *
 * With a meeting bot configured, a booked slot becomes a Zoom meeting at
 * once (BookInterviewMeeting) and the candidate gets the Zoom link in this
 * chat; the AI interviewer joins it at that time. Without one, the link to
 * the BrowseJobs interview room comes on the day.
 *
 * No AI here on purpose: a date choice is a fixed menu, and a wrong guess
 * would book someone on a day they did not pick. Replies are free text
 * inside the 24-hour window the candidate's own message opened.
 */
final readonly class CandidateChatAgent
{
    private const DECLINE = '/^\s*(no|nope|not interested|nahi|not now|can\'?t|cannot|stop|cancel)\b/i';

    /** Wanting it now: "I want now", "can we do it today", "reschedule this for today", "abhi", "aaj". */
    private const NOW = '/\b(now|right now|right away|immediately|asap|abhi|aaj|today)\b/i';

    public function __construct(
        private WhatsAppClient $whatsapp,
        private AuditLogger $audit,
        private MeetingBotClient $bots,
        private OpenInterviewNow $openNow,
    ) {}

    public function handle(EmployerWhatsappMessage $in, InterviewInvite $invite): void
    {
        $text = trim($in->body);
        $invite->update(['replied_at' => now()]);

        // They want to do it now, not on the booked day.
        if (mb_strlen($text) <= 160 && preg_match(self::DECLINE, $text) !== 1 && preg_match(self::NOW, $text) === 1
            && in_array($invite->status, [InterviewInvite::SENT, InterviewInvite::SCHEDULED], true)) {
            $this->startNow($invite);

            return;
        }

        // On Zoom: the link again, unless they are picking another slot or declining.
        $repick = $invite->status === InterviewInvite::SCHEDULED
            && (preg_match(self::DECLINE, $text) === 1 || $this->pick($text, array_values((array) $invite->date_options)) !== null);
        if ($invite->onZoom() && in_array($invite->status, [InterviewInvite::SCHEDULED, InterviewInvite::OPEN], true) && ! $repick) {
            $this->reply($invite, self::zoomMessage($invite));

            return;
        }

        if ($invite->status === InterviewInvite::OPEN) {
            $this->reply($invite, "Your interview is open. Start here: {$invite->link()}\n\nLog in with this WhatsApp number. It takes about 15 minutes.");

            return;
        }

        if (preg_match(self::DECLINE, $text) === 1) {
            $invite->update(['status' => InterviewInvite::DECLINED]);
            $this->audit->log('employer.interview_invite_declined', $invite, ['round' => $invite->round]);
            if ($invite->onZoom() || $invite->bot_id !== null) {
                CancelInterviewMeeting::dispatch($invite->id);
            }
            SendClientUpdate::dispatch($invite->employer_job_id, "🙅 {$this->candidateName($invite)} declined the {$invite->roundLabel()} interview.");
            $this->reply($invite, 'Thanks for letting us know. We have told the company. All the best!');

            return;
        }

        $options = array_values((array) $invite->date_options);
        $pick = $this->pick($text, $options);

        if ($pick === null) {
            $this->reply($invite, "Please reply with the number of the slot that suits you, or *NOW* to start straight away:\n".$this->menu($invite, $options));

            return;
        }

        $time = SendInterviewInvites::interviewTime($invite);
        $invite->update([
            'status' => InterviewInvite::SCHEDULED,
            'chosen_date' => $pick,
            // On Zoom, the next free half-hour that day; otherwise the slot as offered.
            'scheduled_at' => $this->bots->enabled()
                ? SendInterviewInvites::freeZoomSlot($pick, $time, $invite->id)
                : SendInterviewInvites::slotStart($pick, $time),
        ]);
        $label = $invite->fresh()->slotLabel();
        SendClientUpdate::dispatch($invite->employer_job_id, "📅 {$this->candidateName($invite)} booked the {$invite->roundLabel()} AI interview for {$label}.");

        if ($this->bots->enabled()) {
            // The Zoom meeting and the AI interviewer are booked now; the link follows in a moment.
            $this->reply($invite, "Booked for *{$label}*. I am setting up your Zoom interview now. The link will come here in a moment.\n\nNeed a different slot? Just reply 1, 2 or 3, or *NOW* to start straight away:\n".$this->menu($invite, $options));
            BookInterviewMeeting::dispatch($invite->id);

            return;
        }

        $this->reply($invite, "Booked for *{$label}*. That morning you will get a WhatsApp link to start your AI interview. It takes about 15 minutes on your phone or laptop.\n\nNeed a different day? Just reply 1, 2 or 3, or *NOW* to start straight away:\n".$this->menu($invite, $options));
    }

    /** "Now": open the interview at once (or move the Zoom call to the next free half-hour) and say so. */
    private function startNow(InterviewInvite $invite): void
    {
        $result = $this->openNow->handle($invite);
        $name = $this->candidateName($invite);

        if ($result['mode'] === 'room') {
            SendClientUpdate::dispatch($invite->employer_job_id, "⚡ {$name} chose to take the {$invite->roundLabel()} AI interview now. Their link has been sent.");
            $this->reply($invite, "⚡ Starting your {$invite->roundLabel()} interview now. Open it here: {$result['link']}\n\nLog in with this WhatsApp number. It takes about 15 minutes on your phone or laptop.");

            return;
        }

        if ($result['mode'] === 'zoom' && $result['at'] !== null) {
            $at = $result['at']->copy()->setTimezone('Asia/Kolkata')->format('g:i A');
            SendClientUpdate::dispatch($invite->employer_job_id, "⚡ {$name} asked to take the {$invite->roundLabel()} AI interview now. It starts at {$at}.");
            $this->reply($invite, "Okay, I am setting up your Zoom interview to start at *{$at}*, in about 10 minutes. The link will come here in a moment.");
            BookInterviewMeeting::dispatch($invite->id);

            return;
        }

        $options = array_values((array) $invite->date_options);
        $this->reply($invite, "I could not start it right now. Please reply with the number of the slot that suits you, and I will send your link then:\n".$this->menu($invite, $options));
    }

    private function candidateName(InterviewInvite $invite): string
    {
        return (string) ($invite->candidate?->name ?? 'A candidate');
    }

    /** The Zoom link message, sent when the meeting is booked and whenever they ask again. */
    public static function zoomMessage(InterviewInvite $invite): string
    {
        return "Your {$invite->roundLabel()} interview is on Zoom, *{$invite->slotLabel()}* (India time).\n\n"
            ."Join here: {$invite->zoom_join_url}\n\n"
            .'Join from your phone or laptop at that time, with your camera and microphone on. The BrowseJobs AI interviewer will be in the call. It asks its questions out loud and listens to your answers. It takes about 15–20 minutes and is recorded.';
    }

    /** @param  list<string>  $options */
    private function pick(string $text, array $options): ?string
    {
        $t = mb_strtolower($text);

        // "tomorrow" / "kal": only when tomorrow really is one of the three slots (not a Sunday).
        $tomorrow = now('Asia/Kolkata')->addDay()->toDateString();
        if (preg_match('/\b(tomorrow|kal)\b/', $t) === 1 && in_array($tomorrow, $options, true)) {
            return $tomorrow;
        }

        if (preg_match('/^\D*([1-3])\D*$/', $t, $m) === 1 && isset($options[(int) $m[1] - 1])) {
            return $options[(int) $m[1] - 1];
        }

        foreach ($options as $date) {
            $day = Carbon::parse($date);
            $names = [mb_strtolower($day->format('l')), mb_strtolower($day->format('D'))];
            foreach ($names as $name) {
                if (preg_match('/\b'.preg_quote($name, '/').'\b/', $t) === 1) {
                    return $date;
                }
            }
            if (preg_match('/\b'.$day->day.'(st|nd|rd|th)?\b/', $t) === 1) {
                return $date;
            }
        }

        return null;
    }

    /** @param  list<string>  $options */
    private function menu(InterviewInvite $invite, array $options): string
    {
        $time = SendInterviewInvites::interviewTime($invite);
        $lines = [];
        foreach ($options as $i => $date) {
            $lines[] = ($i + 1).') '.SendInterviewInvites::slotLabel($date, $time);
        }

        return implode("\n", $lines);
    }

    private function reply(InterviewInvite $invite, string $body): void
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
            $message->update(['wamid' => $this->whatsapp->sendMessage($invite->phone, $body)]);
        } catch (Throwable $e) {
            report($e);
            $message->update(['status' => 'failed']);
        }
    }
}

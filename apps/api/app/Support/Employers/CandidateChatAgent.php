<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Actions\Employers\SendInterviewInvites;
use App\Models\EmployerWhatsappMessage;
use App\Models\InterviewInvite;
use App\Support\Audit\AuditLogger;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The message agent's side of a candidate's WhatsApp chat (hiring step 5).
 *
 *   "1" / "2" / "3" / "Tuesday" / "15"  → book that day
 *   "not interested" / "no"              → decline, politely
 *   anything once the round is open      → the link again
 *
 * No AI here on purpose: a date choice is a fixed menu, and a wrong guess
 * would book someone on a day they did not pick. Replies are free text
 * inside the 24-hour window the candidate's own message opened.
 */
final readonly class CandidateChatAgent
{
    private const DECLINE = '/^\s*(no|nope|not interested|nahi|not now|can\'?t|cannot|stop|cancel)\b/i';

    public function __construct(
        private WhatsAppClient $whatsapp,
        private AuditLogger $audit,
    ) {}

    public function handle(EmployerWhatsappMessage $in, InterviewInvite $invite): void
    {
        $text = trim($in->body);
        $invite->update(['replied_at' => now()]);

        if ($invite->status === InterviewInvite::OPEN) {
            $this->reply($invite, "Your interview is open. Start here: {$invite->link()}\n\nLog in with this WhatsApp number. It takes about 15 minutes.");

            return;
        }

        if (preg_match(self::DECLINE, $text) === 1) {
            $invite->update(['status' => InterviewInvite::DECLINED]);
            $this->audit->log('employer.interview_invite_declined', $invite, ['round' => $invite->round]);
            $this->reply($invite, 'Thanks for letting us know. We have told the company. All the best!');

            return;
        }

        $options = array_values((array) $invite->date_options);
        $pick = $this->pick($text, $options);

        if ($pick === null) {
            $this->reply($invite, "Please reply with the number of the day that suits you:\n".$this->menu($options));

            return;
        }

        $invite->update(['status' => InterviewInvite::SCHEDULED, 'chosen_date' => $pick]);
        $label = SendInterviewInvites::label($pick);
        $this->reply($invite, "Booked for *{$label}*. That morning you will get a WhatsApp link to start your AI interview. It takes about 15 minutes on your phone or laptop.\n\nNeed a different day? Just reply 1, 2 or 3:\n".$this->menu($options));
    }

    /** @param  list<string>  $options */
    private function pick(string $text, array $options): ?string
    {
        $t = mb_strtolower($text);

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
    private function menu(array $options): string
    {
        $lines = [];
        foreach ($options as $i => $date) {
            $lines[] = ($i + 1).') '.SendInterviewInvites::label($date);
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

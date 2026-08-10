<?php

declare(strict_types=1);

namespace App\Actions\Funnel;

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Enums\MessageChannel;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Message;
use App\Support\Messaging\Messenger;

/**
 * Day-5 conversion moment (runs within a tenant context): once a bootcamp
 * student has ATTENDED the configured number of days, they get a WhatsApp +
 * email nudge to secure their paid-batch seat — while their momentum is at its
 * peak, two days before the bootcamp ends. Deduped via the message log, so
 * each student is nudged exactly once per funnel.
 */
final readonly class SendBootcampPaymentNudges
{
    public function __construct(private Messenger $messenger) {}

    /** @return int students nudged */
    public function handle(): int
    {
        $threshold = (int) config('funnel.payment_nudge_after_days', 5);
        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
        $nudged = 0;

        $bootcamps = Batch::query()
            ->where('type', BatchType::Bootcamp->value)
            ->where('status', 'active')
            ->with('course')
            ->get();

        foreach ($bootcamps as $bootcamp) {
            $sessionIds = $bootcamp->liveSessions()->pluck('id');

            if ($sessionIds->isEmpty()) {
                continue;
            }

            $members = $bootcamp->members()->whereIn('status', $occupying)->with('student')->get();

            foreach ($members as $member) {
                $student = $member->student;

                if ($student === null) {
                    continue;
                }

                $daysAttended = Attendance::query()
                    ->whereIn('live_session_id', $sessionIds)
                    ->where('user_id', $student->id)
                    ->distinct('live_session_id')
                    ->count('live_session_id');

                if ($daysAttended < $threshold) {
                    continue;
                }

                $alreadyNudged = Message::query()
                    ->where('user_id', $student->id)
                    ->where('template_key', 'bootcamp_payment_nudge')
                    ->exists();

                if ($alreadyNudged) {
                    continue;
                }

                $vars = [
                    'name' => $student->name,
                    'days' => (string) $daysAttended,
                    'course' => $bootcamp->course?->name ?? 'your course',
                    'link' => rtrim((string) config('app.frontend_url', ''), '/').'/dashboard',
                ];

                foreach ([MessageChannel::WhatsApp, MessageChannel::Email] as $channel) {
                    $this->messenger->send($student, 'bootcamp_payment_nudge', $vars, ['channel' => $channel]);
                }

                $nudged++;
            }
        }

        return $nudged;
    }
}

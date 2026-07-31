<?php

declare(strict_types=1);

namespace App\Actions\LiveClasses;

use App\Enums\BatchMemberStatus;
use App\Enums\MessageChannel;
use App\Models\LiveSession;
use App\Support\Messaging\Messenger;

/**
 * Tells every occupying member of a batch that a new class has been put on
 * their calendar (WhatsApp + email), the moment an admin schedules it — the
 * 12h/2h/5min reminder ladder then takes over closer to start time. Used by
 * manually added bootcamp / paid-batch classes; the automated funnel batches
 * announce themselves through their welcome messages instead.
 */
final readonly class AnnounceClassScheduled
{
    public function __construct(private Messenger $messenger) {}

    /** @return int students notified */
    public function handle(LiveSession $session): int
    {
        $batch = $session->batch;

        if ($batch === null) {
            return 0;
        }

        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
        $members = $batch->members()->whereIn('status', $occupying)->with('student')->get();

        $notified = 0;

        foreach ($members as $member) {
            if ($member->student === null) {
                continue;
            }

            $vars = [
                'name' => $member->student->name,
                'title' => $session->title,
                'batch' => $batch->number,
                'when' => $session->scheduled_start->format('D, d M Y H:i'),
                'link' => rtrim((string) config('app.frontend_url', ''), '/').'/classes',
            ];

            foreach ([MessageChannel::WhatsApp, MessageChannel::Email] as $channel) {
                $this->messenger->send($member->student, 'class_scheduled', $vars, ['channel' => $channel]);
            }

            $notified++;
        }

        return $notified;
    }
}

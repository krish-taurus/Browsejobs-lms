<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Employers\OpenInterviewRound;
use App\Jobs\SendInterviewInviteMessage;
use App\Models\InterviewInvite;
use App\Support\Employers\InterviewTemplates;
use Illuminate\Console\Command;

/**
 * Every morning (and hourly after that, in case one was missed): open the
 * interviews candidates booked for today, which WhatsApps them the link (or,
 * for a Zoom interview, reminds them of it).
 *
 * Also sends next-round invites (L2 …) that were waiting for Meta to approve
 * the next-round message, once it is approved.
 */
final class OpenDueInterviewsCommand extends Command
{
    protected $signature = 'interviews:open-due';

    protected $description = 'Open the L1/L2 interviews candidates booked for today and send them the link.';

    public function handle(OpenInterviewRound $open, InterviewTemplates $templates): int
    {
        $today = now('Asia/Kolkata')->toDateString();
        $due = InterviewInvite::withoutGlobalScopes()
            ->where('status', InterviewInvite::SCHEDULED)
            ->whereDate('chosen_date', '<=', $today)
            ->get();

        foreach ($due as $invite) {
            $open->handle($invite);
        }

        $waiting = InterviewInvite::withoutGlobalScopes()
            ->where('status', InterviewInvite::PENDING)
            ->where('round', '!=', 'l1')
            ->whereNotNull('error')
            ->get();
        if ($waiting->isNotEmpty() && $templates->approved(InterviewTemplates::NEXT_ROUND)) {
            foreach ($waiting as $i => $invite) {
                $invite->update(['error' => null]);
                SendInterviewInviteMessage::dispatch($invite->id)->delay(now()->addSeconds($i * 3));
            }
        }

        $this->line("Opened {$due->count()} booked interviews.");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Employers\OpenInterviewRound;
use App\Models\InterviewInvite;
use Illuminate\Console\Command;

/**
 * Every morning (and hourly after that, in case one was missed): open the
 * interviews candidates booked for today, which WhatsApps them the link.
 */
final class OpenDueInterviewsCommand extends Command
{
    protected $signature = 'interviews:open-due';

    protected $description = 'Open the L1/L2 interviews candidates booked for today and send them the link.';

    public function handle(OpenInterviewRound $open): int
    {
        $today = now('Asia/Kolkata')->toDateString();
        $due = InterviewInvite::withoutGlobalScopes()
            ->where('status', InterviewInvite::SCHEDULED)
            ->whereDate('chosen_date', '<=', $today)
            ->get();

        foreach ($due as $invite) {
            $open->handle($invite);
        }

        $this->line("Opened {$due->count()} booked interviews.");

        return self::SUCCESS;
    }
}

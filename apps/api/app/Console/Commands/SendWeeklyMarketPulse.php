<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Engagement\SendWeeklyPulse;
use Illuminate\Console\Command;

/**
 * Weekly Market Pulse over WhatsApp/email — marketing category, opt-in only
 * (the Messenger guard suppresses everyone else).
 */
final class SendWeeklyMarketPulse extends Command
{
    protected $signature = 'pulse:weekly-send';

    protected $description = 'Send the weekly Market Pulse digest to opted-in students';

    public function handle(SendWeeklyPulse $send): int
    {
        $summary = $send->handle();

        $this->info("Attempted {$summary['attempted']} send(s) across {$summary['tenants']} tenant(s); Messenger suppressed non-opted-in recipients.");

        return self::SUCCESS;
    }
}

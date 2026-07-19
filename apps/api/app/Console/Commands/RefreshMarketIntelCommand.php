<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RefreshMarketIntel;
use Illuminate\Console\Command;

final class RefreshMarketIntelCommand extends Command
{
    protected $signature = 'market:refresh';

    protected $description = 'Queue the daily market-intelligence snapshot refresh';

    public function handle(): int
    {
        RefreshMarketIntel::dispatch();
        $this->info('Market-intel refresh queued.');

        return self::SUCCESS;
    }
}

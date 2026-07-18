<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Advice\CalibratePri;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Recalibrates PRI weights from placement outcomes per active tenant (PRD §6.21).
 * A no-op for tenants without enough outcome data — they keep config defaults.
 */
final class RunPriCalibration extends Command
{
    protected $signature = 'pri:calibrate';

    protected $description = 'Calibrate PRI weights from placement-outcome correlations';

    public function handle(CalibratePri $calibrate): int
    {
        $calibrated = 0;

        foreach (Tenant::query()->where('status', 'active')->get() as $tenant) {
            if ($calibrate->handle($tenant) !== null) {
                $calibrated++;
            }
        }

        $this->info("Calibrated PRI weights for {$calibrated} tenant(s).");

        return self::SUCCESS;
    }
}

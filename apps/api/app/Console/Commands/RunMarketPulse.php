<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Engagement\BuildMarketPulse;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Builds the daily Market Pulse digest for every active tenant (PRD §6.19).
 * Billing attaches to the tenant's first admin-typed staff user.
 */
final class RunMarketPulse extends Command
{
    protected $signature = 'digest:market-pulse';

    protected $description = 'Build the daily Market Pulse digest per tenant';

    public function handle(BuildMarketPulse $build): int
    {
        $built = 0;

        foreach (Tenant::query()->where('status', 'active')->get() as $tenant) {
            $billTo = User::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('user_type', 'staff')
                ->orderBy('id')
                ->first();

            if ($billTo === null) {
                continue;
            }

            if ($build->handle($tenant->id, $billTo) !== null) {
                $built++;
            }
        }

        $this->info("Built {$built} Market Pulse digest(s).");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Advice\GenerateAlumniCheckins;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Schedules due alumni 6/12-month check-ins per active tenant (PRD §6.21).
 * Idempotent — safe to run daily.
 */
final class RunAlumniCheckins extends Command
{
    protected $signature = 'alumni:checkins';

    protected $description = 'Schedule due alumni 6/12-month post-placement check-ins';

    public function handle(GenerateAlumniCheckins $generate): int
    {
        $created = 0;

        foreach (Tenant::query()->where('status', 'active')->get() as $tenant) {
            $created += app(TenantContext::class)->run($tenant, fn () => $generate->handle());
        }

        $this->info("Scheduled {$created} alumni check-in(s).");

        return self::SUCCESS;
    }
}

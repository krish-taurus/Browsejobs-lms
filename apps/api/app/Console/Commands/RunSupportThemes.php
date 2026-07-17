<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reports\GenerateSupportThemes;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Weekly support-themes report to admin (PRD §6.13). Per tenant, one report per admin.
 * Mirrors RunCounselorDigests — one admin's failure never aborts the run.
 */
final class RunSupportThemes extends Command
{
    protected $signature = 'digest:support-themes';

    protected $description = 'Send each admin the weekly support themes report';

    public function handle(GenerateSupportThemes $generate): int
    {
        $sent = 0;

        foreach (Tenant::query()->get() as $tenant) {
            app(TenantContext::class)->run($tenant, function () use ($generate, &$sent): void {
                $admins = User::query()
                    ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'super-admin']))
                    ->get();

                foreach ($admins as $admin) {
                    try {
                        if ($generate->handle($admin) !== null) {
                            $sent++;
                        }
                    } catch (Throwable) {
                        // One admin's failure never aborts the run.
                    }
                }
            });
        }

        $this->info("Sent {$sent} support themes report(s).");

        return self::SUCCESS;
    }
}

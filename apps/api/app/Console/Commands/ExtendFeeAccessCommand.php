<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Fees\ExtendFeeAccess;
use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Models\FeePlan;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;

/**
 * Gives a student a few more days before the fee lock applies, and lifts any
 * block already in force. Driven by the CRM's Fee Collections page.
 */
final class ExtendFeeAccessCommand extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'fees:extend-access
        {plan : Fee plan id}
        {--days=5 : Days of access to grant}
        {--reason= : Why the extension was granted}';

    protected $description = 'Extend a student\'s class access past their fee due date';

    public function handle(ExtendFeeAccess $extend): int
    {
        $plan = FeePlan::query()->withoutGlobalScope(TenantScope::class)->find((int) $this->argument('plan'));

        if ($plan === null) {
            $this->error("Fee plan '{$this->argument('plan')}' not found.");

            return self::FAILURE;
        }

        return $this->runForTenant($plan->tenant_id, function () use ($plan, $extend): int {
            $until = $extend->handle(
                $plan,
                (int) $this->option('days'),
                $this->option('reason') !== null ? (string) $this->option('reason') : null,
            );

            $this->info('Access extended to '.$until->format('d M Y').' — classes and recordings stay open until then.');

            return self::SUCCESS;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payments\RestructureFeePlan;
use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Models\FeePlan;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Splits a student's REMAINING fee balance into a different number of EMIs —
 * the "can I pay in smaller instalments?" request. Driven by the CRM's Fee
 * Collections page. Paid instalments and the total owed never change.
 */
final class RestructureFeePlanCommand extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'fees:restructure
        {plan : Fee plan id}
        {count : How many instalments the remaining balance becomes}
        {--start= : Due date of the first new instalment (Y-m-d, defaults to today)}
        {--amounts= : Comma-separated rupee amounts, one per instalment (e.g. 5000,8000,17000), overriding an equal split — must sum to exactly the outstanding balance, and its length wins over {count}}';

    protected $description = 'Re-cut the unpaid balance of a fee plan into a different number of EMIs';

    public function handle(RestructureFeePlan $restructure): int
    {
        $plan = FeePlan::query()->withoutGlobalScope(TenantScope::class)
            ->with('instalments')
            ->find((int) $this->argument('plan'));

        if ($plan === null) {
            $this->error("Fee plan '{$this->argument('plan')}' not found.");

            return self::FAILURE;
        }

        $start = $this->option('start') !== null
            ? Carbon::parse((string) $this->option('start'))
            : null;

        $amountsOption = $this->option('amounts');
        $customAmountsPaise = null;

        if ($amountsOption !== null && trim((string) $amountsOption) !== '') {
            $customAmountsPaise = array_map(
                static fn (string $v): int => (int) round(((float) trim($v)) * 100),
                explode(',', (string) $amountsOption),
            );
        }

        return $this->runForTenant($plan->tenant_id, function () use ($plan, $restructure, $start, $customAmountsPaise): int {
            $result = $restructure->handle($plan, (int) $this->argument('count'), $start, null, null, $customAmountsPaise);

            $this->info($customAmountsPaise !== null
                ? sprintf(
                    '%s split into %d instalment(s) at the amounts given — %d old instalment(s) replaced.',
                    '₹'.number_format($result['unpaid_paise'] / 100, 2),
                    $result['created'],
                    $result['removed'],
                )
                : sprintf(
                    '%s split into %d instalment(s) of about ₹%s — %d old instalment(s) replaced.',
                    '₹'.number_format($result['unpaid_paise'] / 100, 2),
                    $result['created'],
                    number_format(($result['unpaid_paise'] / $result['created']) / 100, 2),
                    $result['removed'],
                ));

            if ($result['links_cancelled'] !== []) {
                $this->info('Cancelled '.count($result['links_cancelled']).' old payment link(s) so they can no longer be paid.');
            }

            if ($result['links_failed'] !== []) {
                $this->warn('Could NOT cancel these payment links — cancel them in Razorpay: '.implode(', ', $result['links_failed']));
            }

            return self::SUCCESS;
        });
    }
}

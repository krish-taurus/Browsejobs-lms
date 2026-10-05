<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payments\SetAgreedFee;
use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Models\FeePlan;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Records what a student actually agreed to pay when it is less than the course
 * price, and re-cuts their remaining instalments to match. Driven by the CRM.
 */
final class SetAgreedFeeCommand extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'fees:set-amount
        {plan : Fee plan id}
        {amount : The agreed total in RUPEES (e.g. 25000)}
        {--count=1 : How many instalments the remaining balance becomes}
        {--start= : Due date of the first new instalment (Y-m-d)}
        {--reason= : Why the fee was reduced}';

    protected $description = 'Set the agreed fee for a student and re-cut their remaining instalments';

    public function handle(SetAgreedFee $setFee): int
    {
        $plan = FeePlan::query()->withoutGlobalScope(TenantScope::class)->with('instalments')->find((int) $this->argument('plan'));

        if ($plan === null) {
            $this->error("Fee plan '{$this->argument('plan')}' not found.");

            return self::FAILURE;
        }

        $agreedPaise = (int) round(((float) $this->argument('amount')) * 100);
        $start = $this->option('start') !== null ? Carbon::parse((string) $this->option('start')) : null;

        return $this->runForTenant($plan->tenant_id, function () use ($plan, $setFee, $agreedPaise, $start): int {
            $result = $setFee->handle(
                $plan,
                $agreedPaise,
                (int) $this->option('count'),
                $start,
                $this->option('reason') !== null ? (string) $this->option('reason') : null,
            );

            $this->info(sprintf(
                'Agreed fee ₹%s (course price ₹%s, discount ₹%s). Already paid ₹%s; ₹%s left in %d instalment(s).',
                number_format($result['agreed_paise'] / 100),
                number_format($plan->total_paise / 100),
                number_format($result['discount_paise'] / 100),
                number_format($result['already_paid_paise'] / 100),
                number_format($result['remaining_paise'] / 100),
                $result['instalments'],
            ));

            return self::SUCCESS;
        });
    }
}

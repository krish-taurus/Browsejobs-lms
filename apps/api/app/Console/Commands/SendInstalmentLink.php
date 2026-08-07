<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payments\SendPaymentLink;
use App\Models\Instalment;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Creates (or refreshes) the Razorpay payment link for an instalment and
 * delivers it to the student over the messaging hub (WhatsApp/email —
 * `payment_link` template). Driven by the CRM's Fee Collections page.
 */
final class SendInstalmentLink extends Command
{
    protected $signature = 'fees:send-link {instalment : Instalment id}';

    protected $description = 'Create and message the Razorpay payment link for an instalment';

    public function handle(SendPaymentLink $send, TenantContext $tenants): int
    {
        $instalment = Instalment::query()->withoutGlobalScopes()->find((int) $this->argument('instalment'));

        if ($instalment === null) {
            $this->error('Instalment not found.');

            return self::FAILURE;
        }

        if ($instalment->status->value === 'paid') {
            $this->warn('Instalment is already paid — nothing to send.');

            return self::SUCCESS;
        }

        $tenant = Tenant::query()->find($instalment->tenant_id);
        if ($tenant === null) {
            $this->error('Instalment has no tenant.');

            return self::FAILURE;
        }

        $instalment = $tenants->run($tenant, fn (): Instalment => $send->handle($instalment));

        $student = $instalment->feePlan?->student;
        $this->info(sprintf(
            'Payment link sent to %s (%s): %s',
            $student?->name ?? 'student',
            $student?->phone ?? $student?->email ?? '?',
            (string) $instalment->payment_link_url,
        ));

        return self::SUCCESS;
    }
}

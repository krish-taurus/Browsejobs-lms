<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Funnel\RolloverMasterclassesToBootcamps;
use App\Enums\BatchType;
use App\Models\Batch;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Move one masterclass batch into its 7-day bootcamp immediately, instead of
 * waiting for the daily sweep (which only picks up masterclasses whose day has
 * already passed). Driven from the CRM's "Move to Bootcamp" button.
 */
final class RollMasterclassToBootcamp extends Command
{
    protected $signature = 'funnel:rollover {batch : batch id or number} {--tenant=1}';

    protected $description = 'Roll a masterclass batch into its bootcamp now';

    public function handle(RolloverMasterclassesToBootcamps $rollover): int
    {
        $tenant = Tenant::query()->find((int) $this->option('tenant'));

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        return app(TenantContext::class)->run($tenant, function () use ($rollover): int {
            $reference = (string) $this->argument('batch');

            $batch = Batch::query()
                ->when(
                    is_numeric($reference),
                    fn ($q) => $q->whereKey((int) $reference),
                    fn ($q) => $q->where('number', $reference),
                )
                ->first();

            if ($batch === null) {
                $this->error("Batch not found: {$reference}");

                return self::FAILURE;
            }

            if ($batch->type !== BatchType::Masterclass) {
                $this->error('Only a masterclass batch can be rolled into a bootcamp.');

                return self::FAILURE;
            }

            if ($batch->status !== 'active') {
                $this->error("That masterclass is already {$batch->status}.");

                return self::FAILURE;
            }

            $result = $rollover->rollOne($batch);

            $this->line((string) json_encode($result));

            return self::SUCCESS;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Funnel\CompleteEndedBootcamps;
use App\Models\Batch;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Move one cohort from its bootcamp stage to paid immediately, instead of
 * waiting for the daily sweep (which only converts bootcamps whose 7 days are
 * already over). Driven from the CRM's "Move to Paid" button.
 */
final class ConvertBootcampToPaid extends Command
{
    protected $signature = 'funnel:convert {batch : batch id or number} {--tenant=1}';

    protected $description = 'Move a bootcamp batch to its paid stage now';

    public function handle(CompleteEndedBootcamps $action): int
    {
        $tenant = Tenant::query()->find((int) $this->option('tenant'));

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        return app(TenantContext::class)->run($tenant, function () use ($action): int {
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

            try {
                $result = $action->convertOne($batch);
            } catch (ValidationException $e) {
                $this->error(collect($e->errors())->flatten()->first() ?? 'Could not convert.');

                return self::FAILURE;
            }

            $this->line((string) json_encode($result));

            return self::SUCCESS;
        });
    }
}

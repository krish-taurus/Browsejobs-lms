<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Fees\ExtendBatchAccess;
use App\Console\Commands\Concerns\ResolvesCrmTargets;
use Illuminate\Console\Command;

/**
 * Keeps every student of a batch in class for a few more days. Driven by the
 * CRM's Live Batches page ("Extend access").
 */
final class ExtendBatchAccessCommand extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'fees:extend-batch-access
        {batch : Batch id or number}
        {--days=10 : Days of access to grant}
        {--reason= : Why the extension was granted}';

    protected $description = 'Extend class access for a whole batch past the fee grace period';

    public function handle(ExtendBatchAccess $extend): int
    {
        $batch = $this->findBatch((string) $this->argument('batch'));

        if ($batch === null) {
            $this->error("Batch '{$this->argument('batch')}' not found.");

            return self::FAILURE;
        }

        return $this->runForTenant($batch->tenant_id, function () use ($batch, $extend): int {
            $result = $extend->handle(
                $batch,
                (int) $this->option('days'),
                $this->option('reason') !== null ? (string) $this->option('reason') : null,
            );

            $this->info(sprintf(
                '%s: access extended for the whole batch to %s (%d student%s). %d fee block%s lifted. Classes and recordings stay open until then.',
                $batch->number,
                $result['until']->format('d M Y'),
                $result['students'],
                $result['students'] === 1 ? '' : 's',
                $result['blocks_lifted'],
                $result['blocks_lifted'] === 1 ? '' : 's',
            ));

            return self::SUCCESS;
        });
    }
}

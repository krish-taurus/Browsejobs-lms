<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Enums\BatchType;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Edits a batch's operational fields (capacity, dates, status, trainer) —
 * driven by the CRM's Live Batches page.
 */
final class UpdateBatchCommand extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'batch:update
        {batch : Batch id or number}
        {--capacity= : New seat cap}
        {--status= : New lifecycle status (active|completed|cancelled)}
        {--type= : Convert the batch type (masterclass|bootcamp|paid|self_paced)}
        {--starts= : New start date (Y-m-d)}
        {--ends= : New end date (Y-m-d)}
        {--trainer= : Trainer user id or email (empty string clears)}';

    protected $description = 'Update a batch (capacity, dates, status, type, trainer)';

    public function handle(): int
    {
        $batch = $this->findBatch((string) $this->argument('batch'));

        if ($batch === null) {
            $this->error("Batch '{$this->argument('batch')}' not found.");

            return self::FAILURE;
        }

        return $this->runForTenant($batch->tenant_id, function () use ($batch): int {
            $updates = [];

            if ($this->option('capacity') !== null) {
                $updates['capacity'] = (int) $this->option('capacity') ?: null;
            }
            if (in_array($this->option('status'), ['active', 'completed', 'cancelled'], true)) {
                $updates['status'] = $this->option('status');
            }
            if ($this->option('type') !== null) {
                $type = BatchType::tryFrom((string) $this->option('type'));

                if ($type === null) {
                    $this->error("Unknown batch type '{$this->option('type')}'.");

                    return self::FAILURE;
                }

                // Converting (e.g. masterclass → paid when a cohort is sold
                // directly) keeps members, classes and history — only the
                // funnel stage the batch represents changes.
                $updates['type'] = $type->value;
            }
            if ($this->option('starts')) {
                $updates['starts_on'] = $this->option('starts');
            }
            if ($this->option('ends')) {
                $updates['ends_on'] = $this->option('ends');
            }

            if ($this->option('trainer') !== null) {
                if ($this->option('trainer') === '') {
                    $updates['trainer_id'] = null;
                } else {
                    $trainer = User::query()->withoutGlobalScope(TenantScope::class)
                        ->where('tenant_id', $batch->tenant_id)
                        ->where(fn ($q) => $q->whereKey(ctype_digit((string) $this->option('trainer')) ? (int) $this->option('trainer') : 0)
                            ->orWhere('email', $this->option('trainer')))
                        ->first();

                    if ($trainer === null) {
                        $this->error("Trainer '{$this->option('trainer')}' not found.");

                        return self::FAILURE;
                    }

                    $updates['trainer_id'] = $trainer->id;
                }
            }

            if ($updates === []) {
                $this->warn('Nothing to update.');

                return self::SUCCESS;
            }

            $batch->update($updates);

            $this->info("Batch {$batch->number} updated: ".implode(', ', array_keys($updates)).'.');

            return self::SUCCESS;
        });
    }
}

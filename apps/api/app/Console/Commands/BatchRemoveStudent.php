<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Roster\RemoveBatchMember;
use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Models\BatchMember;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;

/**
 * Drops a student from a batch (membership kept as `dropped` for history,
 * audited) — driven by the CRM roster.
 */
final class BatchRemoveStudent extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'batch:remove-student
        {batch : Batch id or number}
        {user : Student user id}
        {--reason=Removed from CRM : Why the student is being dropped}';

    protected $description = 'Drop a student from a batch (kept as dropped, audited)';

    public function handle(RemoveBatchMember $remove): int
    {
        $batch = $this->findBatch((string) $this->argument('batch'));

        if ($batch === null) {
            $this->error("Batch '{$this->argument('batch')}' not found.");

            return self::FAILURE;
        }

        return $this->runForTenant($batch->tenant_id, function () use ($batch, $remove): int {
            $member = BatchMember::query()->withoutGlobalScope(TenantScope::class)
                ->where('batch_id', $batch->id)
                ->where('user_id', (int) $this->argument('user'))
                ->first();

            if ($member === null) {
                $this->error('That student is not in this batch.');

                return self::FAILURE;
            }

            $remove->handle($member, (string) $this->option('reason'));

            $this->info("Dropped student #{$member->user_id} from batch {$batch->number}.");

            return self::SUCCESS;
        });
    }
}

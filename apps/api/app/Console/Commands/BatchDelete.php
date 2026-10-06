<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Models\BatchMember;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Delete a batch outright — for a test or mistaken batch, not for one that ran.
 *
 * Dropping a student keeps the membership for history; this does the opposite,
 * and is meant for rows that should never have existed. Everything hanging off
 * the batch (members, sessions, fee plans and their payments) goes with it
 * through the database's own cascades.
 *
 * With --with-students the seated accounts are deleted too. That is a bigger
 * hammer: it takes the person's login, quiz attempts, scores and payments. It
 * refuses to touch staff, and refuses anyone who is also seated in a batch that
 * is not being deleted, so a real student on two courses cannot vanish because
 * one of them was a test.
 */
final class BatchDelete extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'batch:delete
        {batch : Batch id or number}
        {--with-students : Also delete the seated student accounts}
        {--reason=Deleted from CRM : Why the batch is being deleted}';

    protected $description = 'Delete a batch, its sessions and its roster (optionally the student accounts too)';

    public function handle(): int
    {
        $batch = $this->findBatch((string) $this->argument('batch'));

        if ($batch === null) {
            $this->error("Batch '{$this->argument('batch')}' not found.");

            return self::FAILURE;
        }

        return $this->runForTenant($batch->tenant_id, function () use ($batch): int {
            $memberIds = BatchMember::query()->withoutGlobalScope(TenantScope::class)
                ->where('batch_id', $batch->id)
                ->pluck('user_id')
                ->unique();

            $deletable = collect();

            if ($this->option('with-students')) {
                $deletable = $this->accountsSafeToDelete($memberIds, $batch->id);
            }

            $number = $batch->number;
            $sessions = DB::table('live_sessions')->where('batch_id', $batch->id)->count();

            DB::transaction(function () use ($batch, $deletable): void {
                // Members and sessions cascade with the batch; the accounts are
                // removed first so no membership row is left pointing at them.
                User::query()->withoutGlobalScope(TenantScope::class)
                    ->whereIn('id', $deletable)
                    ->delete();

                $batch->delete();
            });

            $this->info(sprintf(
                'Deleted batch %s — %d session(s), %d roster row(s), %d account(s). Reason: %s',
                $number,
                $sessions,
                $memberIds->count(),
                $deletable->count(),
                (string) $this->option('reason'),
            ));

            return self::SUCCESS;
        });
    }

    /**
     * Which of these accounts may be deleted along with the batch.
     *
     * @param  Collection<int, int>  $memberIds
     * @return Collection<int, int>
     */
    private function accountsSafeToDelete($memberIds, int $batchId)
    {
        if ($memberIds->isEmpty()) {
            return collect();
        }

        // Someone seated elsewhere is on a live course; the batch being deleted
        // is not the whole of their record, so their account stays.
        $seatedElsewhere = BatchMember::query()->withoutGlobalScope(TenantScope::class)
            ->whereIn('user_id', $memberIds)
            ->where('batch_id', '!=', $batchId)
            ->pluck('user_id')
            ->unique();

        $keep = $seatedElsewhere->all();

        foreach ($memberIds as $id) {
            $user = User::query()->withoutGlobalScope(TenantScope::class)->find($id);

            if ($user === null) {
                $keep[] = $id;

                continue;
            }

            // Staff accounts are never collateral of a batch clean-up.
            if ($user->user_type !== 'student') {
                $this->warn("Kept #{$id} ({$user->name}) — not a student account.");
                $keep[] = $id;
            }
        }

        foreach (array_unique($keep) as $id) {
            if ($seatedElsewhere->contains($id)) {
                $this->warn("Kept #{$id} — also seated in another batch.");
            }
        }

        return $memberIds->reject(fn (int $id) => in_array($id, $keep, true))->values();
    }
}

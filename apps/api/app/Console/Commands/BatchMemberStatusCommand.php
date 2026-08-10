<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Enums\BatchMemberStatus;
use App\Models\BatchMember;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;

/**
 * Sets a batch member's enrolment status (reserved / payment_pending /
 * enrolled / dropped / transferred / completed / paused) — driven by the CRM
 * roster's status control.
 */
final class BatchMemberStatusCommand extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'batch:member-status
        {batch : Batch id or number}
        {user : Student user id}
        {status : New membership status}';

    protected $description = "Change a student's membership status within a batch";

    public function handle(): int
    {
        $batch = $this->findBatch((string) $this->argument('batch'));

        if ($batch === null) {
            $this->error("Batch '{$this->argument('batch')}' not found.");

            return self::FAILURE;
        }

        $status = BatchMemberStatus::tryFrom((string) $this->argument('status'));

        if ($status === null) {
            $this->error("Unknown membership status '{$this->argument('status')}'.");

            return self::FAILURE;
        }

        return $this->runForTenant($batch->tenant_id, function () use ($batch, $status): int {
            $member = BatchMember::query()->withoutGlobalScope(TenantScope::class)
                ->where('batch_id', $batch->id)
                ->where('user_id', (int) $this->argument('user'))
                ->first();

            if ($member === null) {
                $this->error('That student is not in this batch.');

                return self::FAILURE;
            }

            $member->update([
                'status' => $status->value,
                'enrolled_at' => $member->enrolled_at ?? ($status === BatchMemberStatus::Enrolled ? now() : null),
            ]);

            $this->info("Member #{$member->user_id} in {$batch->number} is now {$status->value}.");

            return self::SUCCESS;
        });
    }
}

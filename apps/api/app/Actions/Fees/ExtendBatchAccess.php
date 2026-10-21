<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Enums\BatchMemberStatus;
use App\Models\AccessBlock;
use App\Models\Batch;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keeps a whole batch in class for a few more days: "extend access" for every
 * student at once, including the many who have no fee plan yet and so cannot be
 * given a per-student extension.
 *
 * It is a dated field on the batch, not a silent lift, so the reason and the
 * deadline are on record. The fee gate honours it for everyone in the batch, the
 * dunning ladder stops blocking them while it runs, and any fee block already in
 * force for this batch is lifted straight away. The lock returns by itself the day
 * after it ends. Nothing about what anyone owes changes, and other batches are
 * untouched. Two grants in a row add up instead of overwriting.
 */
final readonly class ExtendBatchAccess
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @return array{until: Carbon, blocks_lifted: int, students: int}
     */
    public function handle(Batch $batch, int $days, ?string $reason = null, ?User $actor = null): array
    {
        if ($days < 1 || $days > ExtendFeeAccess::MAX_DAYS) {
            throw ValidationException::withMessages([
                'days' => 'Choose between 1 and '.ExtendFeeAccess::MAX_DAYS.' days.',
            ]);
        }

        $from = $batch->accessExtensionActive() ? $batch->access_extended_until->copy() : Carbon::today();
        $until = $from->addDays($days);

        return DB::transaction(function () use ($batch, $until, $reason, $actor, $days): array {
            $batch->forceFill([
                'access_extended_until' => $until->toDateString(),
                'access_extended_reason' => $reason,
            ])->save();

            $lifted = AccessBlock::query()->withoutGlobalScopes()
                ->where('tenant_id', $batch->tenant_id)
                ->where('batch_id', $batch->id)
                ->whereNull('lifted_at')
                ->update([
                    'lifted_at' => now(),
                    'lifted_reason' => 'Batch access extended to '.$until->toDateString(),
                    'updated_at' => now(),
                ]);

            $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
            $students = $batch->members()->whereIn('status', $occupying)->count();

            $this->audit->log(
                action: 'fees.batch_access_extended',
                target: $batch,
                metadata: [
                    'days' => $days,
                    'until' => $until->toDateString(),
                    'reason' => $reason,
                    'blocks_lifted' => $lifted,
                    'students' => $students,
                ],
                actor: $actor,
            );

            return ['until' => $until, 'blocks_lifted' => $lifted, 'students' => $students];
        });
    }
}

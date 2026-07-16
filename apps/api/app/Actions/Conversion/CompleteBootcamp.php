<?php

declare(strict_types=1);

namespace App\Actions\Conversion;

use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Events\BootcampCompleted;
use App\Models\Batch;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Completes a bootcamp and converts every occupying attendee to the linked paid
 * batch (PRD §5 Stage 3 "on bootcamp completion"). Idempotent per attendee; safe
 * to re-run.
 *
 * @phpstan-type CompleteSummary array{converted: int, skipped: list<array{user_id: int, reason: string}>}
 */
final readonly class CompleteBootcamp
{
    public function __construct(
        private ConvertBootcampAttendee $convert,
        private AuditLogger $audit,
    ) {}

    /**
     * @return CompleteSummary
     */
    public function handle(Batch $bootcamp, ?User $actor = null): array
    {
        if ($bootcamp->type !== BatchType::Bootcamp) {
            throw ValidationException::withMessages(['batch' => 'Only a bootcamp batch can be completed for conversion.']);
        }

        $paid = Batch::query()
            ->where('linked_source_batch_id', $bootcamp->id)
            ->where('type', BatchType::Paid->value)
            ->first();

        if ($paid === null) {
            throw ValidationException::withMessages(['batch' => 'Link a target paid batch before completing this bootcamp.']);
        }

        $bootcamp->update(['status' => 'completed']);

        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
        $members = $bootcamp->members()->whereIn('status', $occupying)->with('student')->get();

        $converted = 0;
        $skipped = [];

        foreach ($members as $member) {
            try {
                $this->convert->handle($member, $actor);
                $converted++;
            } catch (ValidationException $e) {
                $skipped[] = [
                    'user_id' => $member->user_id,
                    'reason' => collect($e->errors())->flatten()->first() ?? 'Could not convert.',
                ];
            }
        }

        $this->audit->log(
            action: 'batch.completed',
            target: $bootcamp,
            metadata: ['converted' => $converted, 'paid_batch_id' => $paid->id],
            actor: $actor,
        );

        // Auto-request a testimonial from each attendee (Review-for-Voucher engine).
        BootcampCompleted::dispatch($bootcamp);

        return ['converted' => $converted, 'skipped' => $skipped];
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Conversion;

use App\Enums\BatchType;
use App\Events\MasterclassCompleted;
use App\Models\Batch;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Marks a masterclass batch complete (Platform Spec §3 review ladder, stage 1).
 * Unlike a bootcamp, a masterclass has no paid batch to convert into — completing
 * it simply asks each attendee for a review. Idempotent; safe to re-run (the
 * review request dedups per student+stage).
 */
final readonly class CompleteMasterclass
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Batch $masterclass, ?User $actor = null): int
    {
        if ($masterclass->type !== BatchType::Masterclass) {
            throw ValidationException::withMessages(['batch' => 'Only a masterclass batch can be completed this way.']);
        }

        $masterclass->update(['status' => 'completed']);

        $this->audit->log(
            action: 'masterclass.completed',
            target: $masterclass,
            metadata: [],
            actor: $actor,
        );

        // Ask each attendee for a review (review ladder, stage 1).
        MasterclassCompleted::dispatch($masterclass);

        return (int) $masterclass->members()->count();
    }
}

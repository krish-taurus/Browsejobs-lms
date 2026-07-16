<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Models\AccessBlock;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Notifications\FeeNotifier;

/**
 * Lifts every active access block on a student (PRD §6.8 "payment captured →
 * instant auto-unblock"). Idempotent: no active blocks == no-op.
 */
final readonly class LiftAccessBlocks
{
    public function __construct(
        private AuditLogger $audit,
        private FeeNotifier $notifier,
    ) {}

    public function handle(User $student, string $reason = 'Payment received'): int
    {
        $blocks = AccessBlock::query()
            ->where('user_id', $student->id)
            ->whereNull('lifted_at')
            ->get();

        if ($blocks->isEmpty()) {
            return 0;
        }

        foreach ($blocks as $block) {
            $block->lifted_at = now();
            $block->lifted_reason = $reason;
            $block->save();

            $this->audit->log(
                action: 'fees.access_unblocked',
                target: $block,
                metadata: ['reason' => $reason],
            );
        }

        $this->notifier->unblocked($student);

        return $blocks->count();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Models\Batch;
use App\Models\User;
use App\Support\Messaging\Messenger;

/**
 * Tells a trainer or mentor they've been allocated to a batch (PRD §6.3): which
 * batch, and what they'll be doing (which modules, lead trainer, or mentor). Sent
 * when the allocation is first made so staff know their assignment automatically.
 */
final readonly class NotifyBatchAllocation
{
    public function __construct(private Messenger $messenger) {}

    public function send(?User $user, Batch $batch, string $detail): void
    {
        if ($user === null) {
            return;
        }

        $this->messenger->send($user, 'batch_allocation', [
            'name' => $user->name,
            'batch' => 'Batch '.($batch->number ?? ''),
            'detail' => $detail,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Fees;

/**
 * A student fee plan's dunning state at a point in time. Shared by the ladder
 * command, the student /me/fee-status endpoint, and the admin dunning dashboard.
 */
final readonly class DunningSnapshot
{
    public function __construct(
        public int $feePlanId,
        public int $userId,
        public ?int $nextInstalmentId,
        public int $nextAmountPaise,
        public ?string $nextDueOn,
        public int $daysToDue,     // negative == overdue by |n| days
        public bool $overdue,
        public int $outstandingPaise,
        public ?string $blockLevel, // null|soft|hard
        public ?string $payUrl,
    ) {}
}

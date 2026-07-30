<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a single verification check (PRD-E F9).
 *
 * `Pending` means the candidate has submitted and we are waiting on a
 * provider or a reviewer — it is never used to mean "probably fine". Only
 * `Verified` counts toward the badge, and it can expire.
 */
enum VerificationStatus: string
{
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::Pending => 'In review',
            self::Verified => 'Verified',
            self::Failed => 'Could not verify',
            self::Expired => 'Expired',
        };
    }

    /** Only a live, successful check counts — expiry is a real state. */
    public function counts(): bool
    {
        return $this === self::Verified;
    }

    /** Terminal for the candidate: nothing more for them to do right now. */
    public function isSettled(): bool
    {
        return $this === self::Verified || $this === self::Failed;
    }
}

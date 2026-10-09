<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a Taurus bot task (ADR 0052). Bots report every state except
 * `rejected`, which only a human decision on the console can produce.
 */
enum TaurusTaskStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case NeedsApproval = 'needs_approval';
    case Done = 'done';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function isFinished(): bool
    {
        return in_array($this, [self::Done, self::Failed, self::Rejected], true);
    }

    /**
     * States a bot may report through ingest.
     *
     * @return list<string>
     */
    public static function reportable(): array
    {
        return [self::Queued->value, self::Running->value, self::NeedsApproval->value, self::Done->value, self::Failed->value];
    }

    /** @return list<string> */
    public static function open(): array
    {
        return [self::Queued->value, self::Running->value, self::NeedsApproval->value];
    }
}

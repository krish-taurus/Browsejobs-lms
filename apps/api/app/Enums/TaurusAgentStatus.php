<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a Taurus bot says it is doing (ADR 0052). `offline` can be reported,
 * but the console also derives it from silence (taurus.offline_after_minutes).
 */
enum TaurusAgentStatus: string
{
    case Working = 'working';
    case Thinking = 'thinking';
    case Needs = 'needs';
    case Error = 'error';
    case Idle = 'idle';
    case Offline = 'offline';

    /** Plain-English feed line for a status change. */
    public function sentence(): string
    {
        return match ($this) {
            self::Working => 'is working',
            self::Thinking => 'is thinking',
            self::Needs => 'needs your attention',
            self::Error => 'hit an error',
            self::Idle => 'is idle',
            self::Offline => 'went offline',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Coding-lab languages (PRD §6.5). Judge0 language ids come from config/coding_labs.
 */
enum CodeLanguage: string
{
    case Python = 'python';
    case Sql = 'sql';
    case Bash = 'bash';
    case JavaScript = 'javascript';

    public function judge0Id(): int
    {
        return (int) config("coding_labs.languages.{$this->value}");
    }

    /** Monaco editor language mode. */
    public function monaco(): string
    {
        return match ($this) {
            self::Python => 'python',
            self::Sql => 'sql',
            self::Bash => 'shell',
            self::JavaScript => 'javascript',
        };
    }
}

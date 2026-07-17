<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a quiz's questions were authored (PRD §6.5).
 */
enum QuizSource: string
{
    case Ai = 'ai';
    case Manual = 'manual';
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a lesson transcript was provided (PRD §6.10).
 */
enum NoteSource: string
{
    case Paste = 'paste';
    case Upload = 'upload';
}

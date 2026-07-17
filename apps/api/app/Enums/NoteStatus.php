<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A lesson note's approval state (PRD §6.10). Students see the notes — and the tutor
 * can cite the transcript — only once `Approved`.
 */
enum NoteStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
}

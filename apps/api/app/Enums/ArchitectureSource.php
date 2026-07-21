<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a project's downloadable architecture was produced.
 */
enum ArchitectureSource: string
{
    case None = 'none';     // no architecture yet
    case Ai = 'ai';         // AI-generated structure, rendered to a branded PDF
    case Upload = 'upload'; // a file the trainer uploaded
}

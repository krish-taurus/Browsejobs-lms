<?php

declare(strict_types=1);

namespace App\Enums;

/** Lifecycle of a generated JD-specific mock version (PRD-E F2). */
enum JdMockStatus: string
{
    case Pending = 'pending';
    case Generating = 'generating';
    case Ready = 'ready';
    case Failed = 'failed';
}

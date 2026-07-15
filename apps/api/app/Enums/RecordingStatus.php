<?php

declare(strict_types=1);

namespace App\Enums;

enum RecordingStatus: string
{
    case Pending = 'pending';
    case Stored = 'stored';
    case Failed = 'failed';
}

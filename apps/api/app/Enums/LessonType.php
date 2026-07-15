<?php

declare(strict_types=1);

namespace App\Enums;

enum LessonType: string
{
    case LiveClass = 'live_class';
    case Video = 'video';
    case Notes = 'notes';
    case Quiz = 'quiz';
    case CodingLab = 'coding_lab';
    case Assignment = 'assignment';
    case Project = 'project';
    case MockMilestone = 'mock_milestone';
}

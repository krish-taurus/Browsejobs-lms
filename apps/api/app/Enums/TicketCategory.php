<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketCategory: string
{
    case Payments = 'payments';
    case Technical = 'technical';
    case Mentorship = 'mentorship';
    case Training = 'training';
    case Academic = 'academic';
    case InterviewPrep = 'interview_prep';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Payments => 'Payments',
            self::Technical => 'Technical',
            self::Mentorship => 'Mentorship',
            self::Training => 'Training',
            self::Academic => 'Academic',
            self::InterviewPrep => 'Interview Prep',
            self::Other => 'Other',
        };
    }
}

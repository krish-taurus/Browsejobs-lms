<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The single "Next Best Action" the Coach Panel surfaces (PRD §6.4), chosen by
 * rule priority.
 */
enum NextActionKey: string
{
    case ClearFee = 'clear_fee';
    case PracticeModule = 'practice_module';
    case ReEngage = 're_engage';
    case BookMock = 'book_mock';
    case NextTopic = 'next_topic';
}

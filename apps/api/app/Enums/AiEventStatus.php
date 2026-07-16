<?php

declare(strict_types=1);

namespace App\Enums;

enum AiEventStatus: string
{
    case Ok = 'ok';
    case Failed = 'failed';
    case BudgetExceeded = 'budget_exceeded';
}

<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EmployerInterview;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EmployerInterviewInvited
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly EmployerInterview $interview) {}
}

<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EmployerJob;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class StudentInvitedToApply
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly EmployerJob $job,
        public readonly User $candidate,
    ) {}
}

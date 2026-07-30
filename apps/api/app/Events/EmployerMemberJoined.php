<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EmployerMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EmployerMemberJoined
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly EmployerMember $member) {}
}

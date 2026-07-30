<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EmployerInvite;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EmployerMemberInvited
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly EmployerInvite $invite) {}
}

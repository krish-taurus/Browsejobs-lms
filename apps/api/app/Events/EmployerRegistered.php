<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EmployerWorkspace;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EmployerRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly EmployerWorkspace $workspace) {}
}

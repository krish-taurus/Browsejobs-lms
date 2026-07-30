<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EmployerJob;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EmployerJobPublished
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly EmployerJob $job)
    {
    }
}

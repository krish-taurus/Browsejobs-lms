<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ApplicationStageTransition;
use App\Models\EmployerJobApplication;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ApplicationStageChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly EmployerJobApplication $application,
        public readonly ApplicationStageTransition $transition,
    ) {}
}

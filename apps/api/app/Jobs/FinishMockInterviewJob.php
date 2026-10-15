<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Mocks\FinishMockInterview;
use App\Models\MockInterview;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Builds the scorecard for an interview the candidate has already left. The
 * room closes the moment they press "End interview"; this does the slow part
 * (the AI grading, the points, the employer report) in the background.
 * Idempotent: a finished interview is left alone.
 */
final class FinishMockInterviewJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [20, 120];

    public function __construct(public readonly int $mockId) {}

    public function handle(FinishMockInterview $finish): void
    {
        $interview = MockInterview::query()->withoutGlobalScopes()->find($this->mockId);
        if ($interview === null || $interview->status !== MockInterview::STATUS_IN_PROGRESS) {
            return;
        }

        $tenant = Tenant::query()->find($interview->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, fn () => $finish->handle($interview));
    }
}

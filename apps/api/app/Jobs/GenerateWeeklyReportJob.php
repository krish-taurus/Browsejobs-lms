<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Reports\GenerateWeeklyReport;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued per-student weekly report (PRD §6.10). Isolated per student so one failure or
 * budget hit never aborts the batch. The narrator already fails safe; this also swallows.
 */
final class GenerateWeeklyReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $studentId) {}

    public function handle(GenerateWeeklyReport $action): void
    {
        $student = User::query()->withoutGlobalScopes()->find($this->studentId);
        if ($student === null) {
            return;
        }

        $tenant = Tenant::query()->find($student->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($student, $action): void {
            try {
                $action->handle($student);
            } catch (Throwable) {
                // Never abort the scheduled batch.
            }
        });
    }
}

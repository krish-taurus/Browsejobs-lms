<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Employers\BuildInterviewReport;
use App\Events\EmployerInterviewGraded;
use App\Models\EmployerInterview;
use App\Support\Employers\InterviewReportDelivery;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Every graded round — pass or fail — becomes a PDF report (scores,
 * answers, integrity flags with stills) that goes to the client's WhatsApp
 * with the candidate's CV. A report that cannot be built never holds up the
 * round's other consequences (the next-round invite, the client's one-line
 * update); it is logged and the candidate page still has everything.
 */
final class SendInterviewReport implements ShouldQueue
{
    public int $tries = 1;

    public function __construct(
        private readonly BuildInterviewReport $build,
        private readonly InterviewReportDelivery $delivery,
    ) {}

    public function handle(EmployerInterviewGraded $event): void
    {
        $interview = EmployerInterview::withoutGlobalScopes()->find($event->interview->id);
        if ($interview === null) {
            return;
        }

        try {
            app(TenantContext::class)->run($interview->tenant, function () use ($interview): void {
                $report = $this->build->handle($interview);
                if ($report !== null) {
                    $this->delivery->deliver($report);
                }
            });
        } catch (Throwable $e) {
            report($e);
        }
    }
}

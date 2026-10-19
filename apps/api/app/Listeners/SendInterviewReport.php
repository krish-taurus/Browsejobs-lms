<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Employers\BuildInterviewReport;
use App\Actions\Mocks\AnalyseInterviewVoices;
use App\Events\EmployerInterviewGraded;
use App\Models\EmployerInterview;
use App\Models\MockInterview;
use App\Support\Employers\InterviewReportDelivery;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
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
    use InteractsWithQueue;

    /** One run, plus up to a dozen waits for the recording to finish uploading (see waitsForRecording). */
    public int $tries = 14;

    /** How long after the interview ended the report will still wait for its recording. */
    private const WAIT_MINUTES = 12;

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

        // The recording is uploaded in slices and joined in the background after the candidate
        // presses End, so it can still be on its way when the interview is scored. The report
        // waits for it (up to WAIT_MINUTES) so the voice check can listen to it first.
        if ($this->waitsForRecording($interview)) {
            $this->release(60);

            return;
        }

        try {
            app(TenantContext::class)->run($interview->tenant, function () use ($interview): void {
                $this->checkVoices($interview);
                $report = $this->build->handle($interview);
                if ($report !== null) {
                    $this->delivery->deliver($report);
                }
            });
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function waitsForRecording(EmployerInterview $interview): bool
    {
        if ($interview->mock_interview_id === null) {
            return false;
        }
        $mock = MockInterview::query()->withoutGlobalScopes()->find($interview->mock_interview_id);
        if ($mock === null || $mock->recording_url !== null || ! $mock->is_room) {
            return false;
        }

        $ended = $mock->ended_at ?? $mock->completed_at;

        return $ended !== null && $ended->gt(now()->subMinutes(self::WAIT_MINUTES)) && $this->attempts() < $this->tries;
    }

    /** Listen to the whole recording once for a second voice. Never holds the report up if it cannot. */
    private function checkVoices(EmployerInterview $interview): void
    {
        $mock = $interview->mock_interview_id !== null ? MockInterview::query()->withoutGlobalScopes()->find($interview->mock_interview_id) : null;
        if ($mock === null) {
            return;
        }

        try {
            app(AnalyseInterviewVoices::class)->handle($mock);
        } catch (Throwable $e) {
            report($e);
        }
    }
}

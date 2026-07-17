<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\BatchMemberStatus;
use App\Enums\ReportType;
use App\Models\BatchMember;
use App\Models\InAppNotification;
use App\Models\Report;
use App\Models\User;
use App\Support\Messaging\Messenger;
use App\Support\Reports\ReportNarrator;
use App\Support\Scoring\ScoreCalculator;
use App\Support\Tenancy\TenantContext;

/**
 * Generates a student's weekly progress report (PRD §6.10): narrates the P3.2 scoring
 * data (billed to the student, fail-safe) and delivers a magic link + in-app notice.
 * Idempotent per ISO week — re-running updates the row and never double-sends.
 */
final readonly class GenerateWeeklyReport
{
    public function __construct(
        private ScoreCalculator $calculator,
        private ReportNarrator $narrator,
        private Messenger $messenger,
    ) {}

    public function handle(User $student): ?Report
    {
        return app(TenantContext::class)->run($student->tenant, function () use ($student): ?Report {
            $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());
            $enrolled = BatchMember::query()
                ->where('user_id', $student->id)
                ->whereIn('status', $occupying)
                ->exists();

            if (! $enrolled) {
                return null; // not in an active batch — nothing to report
            }

            $data = $this->calculator->computeFor($student);
            $narration = $this->narrator->narrate($student, ReportType::WeeklyStudent, [
                'name' => $student->name,
                'went_well' => implode(', ', $data['went_well']),
                'needs_work' => implode(', ', $data['needs_work']),
                'next_action' => $data['next_action']['title'] ?? '',
                'mastery_summary' => $this->masterySummary($data['mastery']),
            ]);

            $periodStart = now()->startOfWeek();

            // Key on the Carbon (not a string) so the date cast formats the WHERE and
            // INSERT identically — otherwise updateOrCreate re-inserts and collides.
            $report = Report::query()->updateOrCreate(
                ['recipient_id' => $student->id, 'type' => ReportType::WeeklyStudent->value, 'period_start' => $periodStart],
                [
                    'tenant_id' => $student->tenant_id,
                    'title' => 'Your week — '.$periodStart->format('j M'),
                    'narrative' => $narration['narrative'],
                    'data' => $data,
                    'period_end' => now()->endOfWeek(),
                    'ai_event_id' => $narration['ai_event_id'],
                ],
            );

            if ($report->wasRecentlyCreated) {
                $this->deliver($student, $report);
            }

            return $report;
        });
    }

    private function deliver(User $student, Report $report): void
    {
        $this->messenger->send($student, 'weekly_report', ['name' => $student->name], [
            'magic' => [
                'action' => 'report.view',
                'payload' => ['report_id' => $report->id, 'redirect' => '/reports/'.$report->id],
            ],
        ]);

        InAppNotification::query()->create([
            'tenant_id' => $student->tenant_id,
            'user_id' => $student->id,
            'title' => 'This week’s report is ready',
            'body' => 'See what went well, what to work on, and your one focus.',
            'url' => '/reports/'.$report->id,
        ]);
    }

    /**
     * @param  array<int, array{name: string, pct: int, band: string}>  $mastery
     */
    private function masterySummary(array $mastery): string
    {
        if ($mastery === []) {
            return 'no modules scored yet';
        }

        return collect($mastery)
            ->map(fn (array $m) => "{$m['name']} {$m['pct']}%")
            ->implode(', ');
    }
}

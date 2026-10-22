<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

use App\Actions\Employers\BuildInterviewReport;
use App\Models\EmployerInterviewReport;
use App\Models\EmployerJob;
use App\Models\MockInterview;
use App\Support\Mocks\RecordingLink;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * "Show me Kunal's report": the interview report and the recording, as links in
 * the chat itself. The report link opens the PDF for a few hours; the recording
 * link needs no login and lasts a week, as it does on WhatsApp.
 */
final class InterviewReportLinks
{
    private const LINK_HOURS = 6;

    /** @param  list<string>  $names */
    public function reply(EmployerJob $job, array $names, ?string $round, ?int $topN): string
    {
        $reports = EmployerInterviewReport::query()
            ->where('employer_job_id', $job->id)
            ->with(['interview.application.candidate:id,name'])
            ->latest('id')
            ->get()
            ->filter(fn (EmployerInterviewReport $r): bool => $r->interview !== null && ($round === null || $r->interview->round === $round))
            ->unique(fn (EmployerInterviewReport $r): string => $r->interview->employer_job_application_id.'-'.$r->interview->round)
            ->values();

        $label = $round !== null ? strtoupper($round).' ' : '';
        if ($reports->isEmpty()) {
            return "There is no finished {$label}interview report for {$job->title} yet. A report appears once a candidate finishes the interview and it is graded.";
        }

        $nameOf = static fn (EmployerInterviewReport $r): string => (string) ($r->interview->application?->candidate?->name ?? 'A candidate');

        if ($names !== []) {
            $picked = collect();
            foreach ($names as $said) {
                $hits = $reports->filter(fn (EmployerInterviewReport $r): bool => HiringActionPlanner::nameMatches($said, $nameOf($r)));
                if ($hits->isEmpty()) {
                    return "I could not find a report for \"{$said}\" on {$job->title}. Reports I have: ".$reports->map($nameOf)->unique()->implode(', ').'.';
                }
                $picked = $picked->merge($hits);
            }
            $reports = $picked->unique('id')->values();
        } elseif ($topN !== null) {
            $reports = $reports->sortByDesc(fn (EmployerInterviewReport $r): int => (int) ($r->interview->overall_score ?? -1))->take($topN)->values();
        } elseif ($reports->count() > 3) {
            return "I have {$reports->count()} {$label}reports for {$job->title}: "
                .$reports->map(fn (EmployerInterviewReport $r): string => $nameOf($r).' ('.strtoupper($r->interview->round).($r->interview->overall_score !== null ? ' '.$r->interview->overall_score.'/100' : '').')')->implode(', ')
                .'. Tell me whose report you want, for example "show Kunal\'s report".';
        }

        return $reports->map(fn (EmployerInterviewReport $r): string => $this->block($r, $nameOf($r)))->implode("\n\n");
    }

    private function block(EmployerInterviewReport $report, string $name): string
    {
        $interview = $report->interview;
        $score = $interview->overall_score !== null ? " {$interview->overall_score}/100" : '';
        $lines = ["{$name}: ".strtoupper($interview->round)."{$score}."];

        try {
            if ($report->pdf_path !== null) {
                $lines[] = 'Report: '.Storage::disk(BuildInterviewReport::DISK)->temporaryUrl($report->pdf_path, now()->addHours(self::LINK_HOURS));
            }
            $mock = $interview->mock_interview_id !== null ? MockInterview::query()->withoutGlobalScopes()->find($interview->mock_interview_id) : null;
            if ($mock !== null && $mock->recording_url !== null) {
                $lines[] = 'Recording: '.RecordingLink::for($mock);
            }
        } catch (Throwable $e) {
            report($e);
            $lines[] = 'The report file could not be linked just now. Try again in a minute.';
        }

        return implode("\n", $lines);
    }
}

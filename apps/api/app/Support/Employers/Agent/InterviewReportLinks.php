<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

use App\Actions\Employers\BuildInterviewReport;
use App\Models\EmployerInterviewReport;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use App\Models\MockInterview;
use App\Support\Mocks\RecordingLink;
use Illuminate\Support\Collection;
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

    /** Links for the web chat. @param  list<string>  $names */
    public function reply(EmployerWorkspace $workspace, ?EmployerJob $job, array $names, ?string $round, ?int $topN): string
    {
        $found = $this->find($workspace, $job, $names, $round, $topN);
        if (is_string($found)) {
            return $found;
        }

        return $found->map(fn (EmployerInterviewReport $r): string => $this->block($r, $this->nameOf($r)))->implode("\n\n");
    }

    public function nameOf(EmployerInterviewReport $r): string
    {
        return (string) ($r->interview->application?->candidate?->name ?? 'A candidate');
    }

    /**
     * The reports asked for, or a sentence saying why there are none to give. A named person is looked for on the
     * role given first and then on every role, so nobody has to say which role they are on. A person who is on a
     * role but has no report is told so; another person's report is never given in their place.
     *
     * @param  list<string>  $names
     * @return Collection<int, EmployerInterviewReport>|string
     */
    public function find(EmployerWorkspace $workspace, ?EmployerJob $job, array $names, ?string $round, ?int $topN): Collection|string
    {
        $all = $this->reports($workspace, null, $round);
        $reports = $job !== null ? $all->filter(fn (EmployerInterviewReport $r): bool => $r->employer_job_id === $job->id)->values() : $all;
        $label = $round !== null ? strtoupper($round).' ' : '';

        if ($names !== []) {
            $picked = collect();
            foreach ($names as $said) {
                $hits = $reports->filter(fn (EmployerInterviewReport $r): bool => HiringActionPlanner::nameMatches($said, $this->nameOf($r)));
                if ($hits->isEmpty() && $job !== null) {
                    $hits = $all->filter(fn (EmployerInterviewReport $r): bool => HiringActionPlanner::nameMatches($said, $this->nameOf($r)));
                }
                if ($hits->isEmpty()) {
                    return $this->noReportFor($workspace, $said, $label, $all);
                }
                $picked = $picked->merge($hits);
            }

            return $picked->unique('id')->values();
        }

        if ($reports->isEmpty()) {
            return 'There is no finished '.$label.'interview report'.($job !== null ? " for {$job->title}" : '').' yet. A report appears once a candidate finishes the interview and it is graded.';
        }
        if ($topN !== null) {
            return $reports->sortByDesc(fn (EmployerInterviewReport $r): int => (int) ($r->interview->overall_score ?? -1))->take($topN)->values();
        }
        if ($reports->count() > 3) {
            return "I have {$reports->count()} {$label}reports"
                .($job !== null ? " for {$job->title}" : '').': '
                .$reports->map(fn (EmployerInterviewReport $r): string => $this->nameOf($r).' ('.strtoupper($r->interview->round).($r->interview->overall_score !== null ? ' '.$r->interview->overall_score.'/100' : '').')')->implode(', ')
                .'. Tell me whose report you want, for example "show Kunal\'s report".';
        }

        return $reports;
    }

    /** @param  Collection<int, EmployerInterviewReport>  $all */
    private function noReportFor(EmployerWorkspace $workspace, string $said, string $label, Collection $all): string
    {
        $applicant = EmployerJobApplication::query()
            ->whereIn('employer_job_id', EmployerJob::query()->select('id')->where('employer_workspace_id', $workspace->id))
            ->with(['candidate:id,name', 'job:id,title'])
            ->get()
            ->first(fn (EmployerJobApplication $a): bool => HiringActionPlanner::nameMatches($said, (string) ($a->candidate?->name ?? '')));

        if ($applicant !== null) {
            return $applicant->candidate->name.($applicant->job !== null ? " ({$applicant->job->title})" : '')
                ." does not have a finished {$label}interview report yet. A report is made once the interview is taken and scored.";
        }

        return "I could not find anyone called \"{$said}\"."
            .($all->isNotEmpty() ? ' Reports I have: '.$all->map(fn (EmployerInterviewReport $r): string => $this->nameOf($r))->unique()->implode(', ').'.' : '');
    }

    /** @return Collection<int, EmployerInterviewReport> */
    private function reports(EmployerWorkspace $workspace, ?EmployerJob $job, ?string $round): Collection
    {
        return EmployerInterviewReport::query()
            ->whereIn('employer_job_id', EmployerJob::query()->select('id')->where('employer_workspace_id', $workspace->id))
            ->when($job !== null, fn ($q) => $q->where('employer_job_id', $job->id))
            ->with(['interview.application.candidate:id,name'])
            ->latest('id')
            ->get()
            ->filter(fn (EmployerInterviewReport $r): bool => $r->interview !== null && ($round === null || $r->interview->round === $round))
            ->unique(fn (EmployerInterviewReport $r): string => $r->interview->employer_job_application_id.'-'.$r->interview->round)
            ->values();
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

<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWorkspace;
use Illuminate\Support\Str;

/**
 * What a client can ask Taurus beyond the counts: which roles they have and what
 * each asks for, and, person by person, where each candidate is and how each
 * interview went. Names and results only. Never a phone number, an email, a CV
 * or an answer, because none of that is read here.
 */
final class HiringFloorDetail
{
    private const MAX_JOBS = 10;

    private const MAX_PEOPLE = 40;

    /** @return list<string> */
    public function lines(EmployerWorkspace $workspace, ?EmployerJob $scope): array
    {
        $jobs = $scope !== null
            ? collect([$scope])
            : EmployerJob::query()->where('employer_workspace_id', $workspace->id)->latest('id')->limit(self::MAX_JOBS)->get();
        if ($jobs->isEmpty()) {
            return ['Roles: none yet.'];
        }

        $jobIds = $jobs->pluck('id')->all();
        $applicants = EmployerJobApplication::query()->whereIn('employer_job_id', $jobIds)->selectRaw('employer_job_id, count(*) as n')->groupBy('employer_job_id')->pluck('n', 'employer_job_id');

        $lines = ['Roles (id, status, what each asks for):'];
        foreach ($jobs as $job) {
            $min = $job->experience_min_years;
            $max = $job->experience_max_years;
            $experience = $max !== null ? "{$min} to {$max} years" : "{$min}+ years";
            $where = $job->remote ? 'remote' : (($job->locations ?? []) !== [] ? implode(', ', $job->locations) : 'location not set');
            $skills = ($job->skills ?? []) !== [] ? implode(', ', array_slice($job->skills, 0, 8)) : 'none listed';
            $about = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $job->description)) ?? ''), 160);
            $lines[] = "- #{$job->id} {$job->title} ({$job->status->value}): {$job->openings} opening(s), {$experience}, {$where}. Skills: {$skills}. {$applicants->get($job->id, 0)} applicant(s). About: {$about}";
        }

        $applications = EmployerJobApplication::query()
            ->whereIn('employer_job_id', $jobIds)
            ->with('candidate:id,name')
            ->latest('updated_at')
            ->limit(self::MAX_PEOPLE)
            ->get();
        $total = (int) $applicants->sum();

        if ($applications->isEmpty()) {
            return [...$lines, 'People: nobody has applied yet.'];
        }

        $interviews = EmployerInterview::query()
            ->whereIn('employer_job_application_id', $applications->pluck('id'))
            ->get(['employer_job_application_id', 'round', 'status', 'overall_score', 'invited_at'])
            ->groupBy('employer_job_application_id');
        $titles = $jobs->pluck('title', 'id');

        $lines[] = 'People (name, role, stage, results). No contact details are held here'.($total > self::MAX_PEOPLE ? "; the {$applications->count()} most recently active of {$total}" : '').':';
        foreach ($applications as $application) {
            $name = trim((string) ($application->candidate?->name ?? '')) ?: 'A candidate';
            $stage = $application->stage->value;
            $parts = ["stage {$stage}"];
            $parts[] = $application->mock_score !== null ? "screening score {$application->mock_score}/100" : 'no screening score';
            foreach (($interviews->get($application->id) ?? collect())->sortBy('round') as $interview) {
                $parts[] = strtoupper($interview->round).' '.$this->interviewState($interview);
            }
            if ($application->stage->value === 'rejected' && $application->rejection_reason) {
                $parts[] = 'rejected: '.Str::limit($application->rejection_reason, 80);
            }
            $lines[] = "- {$name} ({$titles->get($application->employer_job_id)}): ".implode('; ', $parts).'.';
        }

        return $lines;
    }

    private function interviewState(EmployerInterview $interview): string
    {
        return match ($interview->status->value) {
            'graded' => $interview->overall_score !== null ? "scored {$interview->overall_score}/100" : 'graded',
            'submitted' => 'finished, waiting to be graded',
            'in_progress' => 'in progress',
            'expired' => 'expired without being taken',
            default => 'invited'.($interview->invited_at !== null ? ' '.(int) $interview->invited_at->diffInDays(now()).' day(s) ago, not started' : ', not started'),
        };
    }
}

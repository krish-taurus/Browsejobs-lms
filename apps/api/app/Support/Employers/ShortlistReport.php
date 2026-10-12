<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Enums\EmployerJobStatus;
use App\Models\EmployerJob;
use App\Models\EmployerWhatsappLink;
use App\Models\JobShortlistEntry;
use App\Models\JobShortlistRun;
use Illuminate\Support\Str;

/**
 * The shortlist agent's report for a WhatsApp chat: who it found for a JD,
 * from which source, how well they match, and why. A BrowseJobs student's
 * phone or email is never in it (the client's own candidates are theirs).
 */
final class ShortlistReport
{
    private const SHOW = 10;

    private const SOURCE = ['browsejobs' => 'BrowseJobs', 'client_db' => 'Your database', 'naukri' => 'Naukri'];

    /** The report for the latest job this chat posted, else the workspace's latest live job. */
    public function latestFor(EmployerWhatsappLink $link): string
    {
        $job = EmployerJob::query()->where('whatsapp_link_id', $link->id)->latest('id')->first()
            ?? EmployerJob::query()->where('employer_workspace_id', $link->employer_workspace_id)
                ->where('status', EmployerJobStatus::Published->value)->latest('published_at')->first();

        return $job === null
            ? "You have no live job yet. Tell me about a role and I will post it and find candidates.\n\nFor example: _\"We need 2 Data Engineers in Bengaluru, 3–5 years, Python, SQL.\"_"
            : $this->text($job);
    }

    public function text(EmployerJob $job): string
    {
        $url = rtrim((string) config('app.frontend_url'), '/')."/employer/jobs/{$job->id}?tab=shortlist";
        $head = "📋 *Shortlist · {$job->title}*\n".$this->facts($job);

        $run = JobShortlistRun::query()->where('employer_job_id', $job->id)->latest('id')->first();
        if ($run === null) {
            return "{$head}\n\nThe shortlist agent has not run on this job yet. It runs when the job goes live — send *SHORTLIST* again in a minute.\n\n{$url}";
        }

        $looked = collect($run->considered ?? [])->map(fn ($c, $s) => (self::SOURCE[$s] ?? $s).' '.(int) ($c['considered'] ?? 0))->implode(' · ');
        $total = collect($run->considered ?? [])->sum(fn ($c) => (int) ($c['considered'] ?? 0));

        $entries = JobShortlistEntry::query()
            ->where('employer_job_id', $job->id)
            ->where('status', JobShortlistEntry::STATUS_SHORTLISTED)
            ->with(['employerCandidate', 'candidateUser:id,name'])
            ->orderBy('rank')
            ->get();

        $min = (int) ($run->settings['min_score'] ?? ShortlistSettings::DEFAULT_MIN_SCORE);
        if ($entries->isEmpty() && ! empty($run->near_misses)) {
            $lines = [];
            foreach ($run->near_misses as $i => $n) {
                $skills = (array) ($n['matched_skills'] ?? []);
                $lines[] = ($i + 1).'. *'.Str::limit((string) $n['name'], 40).'* — '.(int) $n['match_score'].'% · '.(self::SOURCE[$n['source']] ?? $n['source'])
                    .($skills !== [] ? "\n    ✓ ".implode(', ', $skills) : '');
            }

            return "{$head}\n\nLooked at {$total} ({$looked}). *Nobody reached your minimum match of {$min}%.*\n\n"
                ."Closest so far:\n".implode("\n", $lines)
                ."\n\nTo shortlist them, lower the minimum match on the AI shortlist page and press Run — or upload your own candidate Excel / Naukri export there.\n\n{$url}";
        }

        if ($entries->isEmpty()) {
            return "{$head}\n\nLooked at {$total} ({$looked}). *Nobody matched yet.*\n\n"
                ."To get matches:\n"
                ."• Upload your candidate Excel or Naukri export on the AI shortlist page, then press Run.\n"
                ."• Or lower the minimum match there.\n\n{$url}";
        }

        $lines = [];
        foreach ($entries->take(self::SHOW) as $i => $e) {
            $name = $e->employerCandidate?->name ?? $e->candidateUser?->name ?? 'Candidate';
            $matched = array_slice((array) ($e->reasons['matched_skills'] ?? []), 0, 4);
            $missing = array_slice((array) ($e->reasons['missing_skills'] ?? []), 0, 2);
            $detail = array_filter([
                $matched !== [] ? '✓ '.implode(', ', $matched) : null,
                $missing !== [] ? 'missing '.implode(', ', $missing) : null,
                $e->source === 'browsejobs' && ($e->reasons['ai_readiness'] ?? null) === null ? 'no AI interview yet' : null,
                $e->reasons['experience'] ?? null,
            ]);

            $lines[] = ($i + 1).'. *'.Str::limit($name, 40)."* — {$e->match_score}% · ".(self::SOURCE[$e->source] ?? $e->source)
                .($detail !== [] ? "\n    ".implode(' · ', $detail) : '');
        }

        $more = $entries->count() > self::SHOW ? "\n…and ".($entries->count() - self::SHOW).' more.' : '';

        return "{$head}\n\nLooked at {$total} ({$looked}) · picked *{$entries->count()}*\n\n"
            .implode("\n", $lines).$more
            ."\n\nCVs, full list and WhatsApp interview invites:\n{$url}";
    }

    private function facts(EmployerJob $job): string
    {
        $where = $job->remote ? 'Remote' : (implode(', ', $job->locations ?? []) ?: 'Any location');
        $years = $job->experience_min_years.($job->experience_max_years !== null ? '–'.$job->experience_max_years : '+').' yrs';

        return "{$where} · {$job->openings} ".Str::plural('opening', $job->openings)." · {$years}";
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Actions\Employers\RunShortlistAgent;
use App\Enums\EmployerInterviewStatus;
use App\Enums\EmployerJobStatus;
use App\Models\EmployerCandidate;
use App\Models\EmployerInterview;
use App\Models\EmployerJob;
use App\Models\EmployerWhatsappLink;
use App\Models\InterviewInvite;
use App\Models\JobShortlistEntry;
use App\Models\JobShortlistRun;
use Illuminate\Support\Collection;
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

        $settings = ShortlistSettings::fromArray((array) $run->settings);
        $rule = $settings->requireInterview
            ? "Rule: first priority CV {$settings->firstCvBar}%+ and AI interview {$settings->firstInterviewBar}%+; second priority CV {$settings->secondCvBar}%+ and AI interview {$settings->secondInterviewBar}%+."
            : null;

        $looked = collect($run->considered ?? [])->map(fn ($c, $s) => (self::SOURCE[$s] ?? $s).' '.(int) ($c['considered'] ?? 0))->implode(' · ');
        $total = collect($run->considered ?? [])->sum(fn ($c) => (int) ($c['considered'] ?? 0));
        $intro = $head.($rule !== null ? "\n{$rule}" : '')
            .(RunShortlistAgent::testUserIds($job) !== null ? "\n🧪 *Test mode:* only your test students are considered; nobody else is messaged." : '');

        $entries = JobShortlistEntry::query()
            ->where('employer_job_id', $job->id)
            ->where('status', JobShortlistEntry::STATUS_SHORTLISTED)
            ->with(['employerCandidate', 'candidateUser:id,name'])
            ->orderBy('rank')
            ->get();
        $awaiting = $this->awaiting($job, $run);

        if ($entries->isEmpty() && $awaiting === '') {
            $near = collect($run->near_misses ?? []);
            if ($near->isEmpty()) {
                return "{$intro}\n\nLooked at {$total} ({$looked}). *Nobody matched yet.*\n\n"
                    ."To get matches:\n"
                    ."• Upload your candidate Excel or Naukri export on the AI shortlist page, then press Run.\n"
                    ."• Or lower the bar there.\n\n{$url}";
            }

            $lines = $near->values()->map(function (array $n, int $i): string {
                $scores = $this->scores($n['cv_match'] ?? null, $n['interview_score'] ?? null, (int) ($n['match_score'] ?? 0));
                $skills = (array) ($n['matched_skills'] ?? []);

                return ($i + 1).'. *'.Str::limit((string) $n['name'], 40)."* — {$scores} · ".(self::SOURCE[$n['source']] ?? $n['source'])
                    .($skills !== [] ? "\n    ✓ ".implode(', ', $skills) : '');
            })->implode("\n");

            $bar = $settings->requireInterview ? "{$settings->secondCvBar}% CV" : "{$settings->minScore}%";

            return "{$intro}\n\nLooked at {$total} ({$looked}). Nobody reached your minimum match of {$bar} yet.\n\n"
                ."Closest so far:\n{$lines}\n\n"
                ."To shortlist them, lower the bar on the AI shortlist page and press Run — or upload your own candidate Excel / Naukri export there.\n\n{$url}";
        }

        $out = "{$intro}\n\nLooked at {$total} ({$looked}) · picked *{$entries->count()}*";

        if ($entries->isNotEmpty()) {
            $groups = $settings->requireInterview
                ? $entries->groupBy(fn (JobShortlistEntry $e) => (int) ($e->reasons['tier'] ?? 2))->sortKeys()
                : collect([0 => $entries]);
            $n = 0;
            foreach ($groups as $tier => $group) {
                $title = match ($tier) {
                    1 => "\n\n⭐ *First priority* (CV {$settings->firstCvBar}%+ · AI interview {$settings->firstInterviewBar}%+)",
                    2 => "\n\n✅ *Second priority* (CV {$settings->secondCvBar}%+ · AI interview {$settings->secondInterviewBar}%+)",
                    default => '',
                };
                $out .= $title === '' ? "\n" : $title;
                foreach ($group as $e) {
                    if (++$n > self::SHOW) {
                        break 2;
                    }
                    $out .= "\n".$this->line($n, $e);
                }
            }
            if ($entries->count() > self::SHOW) {
                $out .= "\n…and ".($entries->count() - self::SHOW).' more.';
            }
        } else {
            $out .= "\n\nNobody has cleared both the CV and the AI interview yet.";
        }

        if ($awaiting !== '') {
            $out .= "\n\n".$awaiting;
        }

        if ($entries->isNotEmpty() && $settings->autoInvite) {
            $out .= "\n\n📩 Everyone shortlisted is sent a WhatsApp to pick a slot for their L1 AI interview — automatically.";
        }

        $progress = $this->progress($job);
        if ($progress !== '') {
            $out .= "\n\n".$progress;
        }

        $cleared = $this->cleared($job, $settings);
        if ($cleared !== '') {
            $out .= "\n\n".$cleared;
        }

        return $out."\n\nCVs, full list and interview status:\n{$url}";
    }

    private function line(int $n, JobShortlistEntry $e): string
    {
        $name = $e->employerCandidate?->name ?? $e->candidateUser?->name ?? 'Candidate';
        $matched = array_slice((array) ($e->reasons['matched_skills'] ?? []), 0, 4);
        $missing = array_slice((array) ($e->reasons['missing_skills'] ?? []), 0, 2);
        $detail = array_filter([
            $matched !== [] ? '✓ '.implode(', ', $matched) : null,
            $missing !== [] ? 'missing '.implode(', ', $missing) : null,
            $e->reasons['experience'] ?? null,
        ]);
        $scores = $this->scores($e->reasons['cv_match'] ?? null, $e->reasons['interview_score'] ?? null, (int) $e->match_score);

        return "{$n}. *".Str::limit($name, 40)."* — {$scores} · ".(self::SOURCE[$e->source] ?? $e->source)
            .($detail !== [] ? "\n    ".implode(' · ', $detail) : '');
    }

    /** "CV 90% · AI interview 80%", or the single match % for older runs. */
    private function scores(mixed $cv, mixed $interview, int $match): string
    {
        if ($cv === null) {
            return "{$match}%";
        }

        return "CV {$cv}% · ".($interview === null ? 'no AI interview yet' : "AI interview {$interview}%");
    }

    /** People sent the AI screening interview, with where they are. */
    private function awaiting(EmployerJob $job, JobShortlistRun $run): string
    {
        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect($run->awaiting ?? []);
        if ($rows->isEmpty()) {
            return '';
        }

        $accountOf = EmployerCandidate::query()
            ->whereIn('id', $rows->pluck('employer_candidate_id')->filter()->all())
            ->pluck('user_id', 'id');
        $invites = InterviewInvite::query()
            ->where('employer_job_id', $job->id)
            ->where('round', InterviewInvite::ROUND_SCREEN)
            ->get()
            ->keyBy('candidate_user_id');

        $lines = $rows->take(self::SHOW)->map(function (array $r) use ($accountOf, $invites): string {
            $userId = $r['candidate_user_id'] ?? $accountOf[$r['employer_candidate_id'] ?? 0] ?? null;
            $invite = $userId !== null ? $invites->get($userId) : null;
            $state = match ($invite?->status) {
                null => 'invite not sent yet',
                InterviewInvite::FAILED => 'could not be messaged',
                InterviewInvite::DONE => 'interview finished',
                default => 'AI interview invite sent',
            };

            return '• *'.Str::limit((string) $r['name'], 40)."* — CV {$r['cv_match']}% · {$state}";
        })->implode("\n");
        $more = $rows->count() > self::SHOW ? "\n…and ".($rows->count() - self::SHOW).' more.' : '';

        return "⏳ *Taking the AI interview* ({$rows->count()}) — their CV matches; they are shortlisted if the interview clears the bar too:\n{$lines}{$more}";
    }

    /** "🎙 Interviews: L1 booked 2 · L1 done 1 · L2 booked 1" */
    private function progress(EmployerJob $job): string
    {
        $labels = [InterviewInvite::SENT => 'asked', InterviewInvite::SCHEDULED => 'booked', InterviewInvite::OPEN => 'today', InterviewInvite::DONE => 'done', InterviewInvite::DECLINED => 'declined'];
        $parts = InterviewInvite::query()
            ->where('employer_job_id', $job->id)
            ->where('round', '!=', InterviewInvite::ROUND_SCREEN)
            ->whereIn('status', array_keys($labels))
            ->selectRaw('round, status, count(*) as n')
            ->groupBy('round', 'status')
            ->orderBy('round')
            ->get()
            ->map(fn ($r) => strtoupper((string) $r->round).' '.$labels[$r->status].' '.$r->n);

        return $parts->isEmpty() ? '' : '🎙 Interviews: '.$parts->implode(' · ');
    }

    /** Everyone who cleared every AI round at or above the pass mark, with their scores. */
    private function cleared(EmployerJob $job, ShortlistSettings $settings): string
    {
        $aiRounds = app(InterviewProcess::class)->forJob($job)
            ->filter(fn ($r) => $r->enabled && $r->isPlatformRun())
            ->pluck('key')
            ->values();
        if ($aiRounds->isEmpty()) {
            return '';
        }

        $rows = EmployerInterview::query()
            ->whereHas('application', fn ($q) => $q->where('employer_job_id', $job->id))
            ->where('status', EmployerInterviewStatus::Graded->value)
            ->whereIn('round', $aiRounds->all())
            ->with('application.candidate:id,name')
            ->get()
            ->groupBy('employer_job_application_id')
            ->filter(fn (Collection $rounds) => $aiRounds->every(
                fn (string $key) => ($rounds->firstWhere('round', $key)?->overall_score ?? -1) >= $settings->roundPassMark
            ));
        if ($rows->isEmpty()) {
            return '';
        }

        $base = rtrim((string) config('app.frontend_url'), '/')."/employer/jobs/{$job->id}/candidates/";
        $lines = $rows->values()->map(function (Collection $rounds, int $i) use ($aiRounds, $base): string {
            $first = $rounds->first();
            $scores = $aiRounds->map(fn (string $key) => strtoupper($key).' '.$rounds->firstWhere('round', $key)?->overall_score)->implode(' · ');

            return ($i + 1).'. *'.Str::limit((string) ($first->application?->candidate?->name ?? 'Candidate'), 40)."* — {$scores}\n    {$base}{$first->employer_job_application_id}";
        })->implode("\n");

        return "🏆 *Cleared the AI interviews* ({$rows->count()}):\n{$lines}";
    }

    private function facts(EmployerJob $job): string
    {
        $where = $job->remote ? 'Remote' : (implode(', ', $job->locations ?? []) ?: 'Any location');
        $years = $job->experience_min_years.($job->experience_max_years !== null ? '–'.$job->experience_max_years : '+').' yrs';

        return "{$where} · {$job->openings} ".Str::plural('opening', $job->openings)." · {$years}";
    }
}

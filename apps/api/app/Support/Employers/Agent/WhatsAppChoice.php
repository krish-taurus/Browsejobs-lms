<?php

declare(strict_types=1);

namespace App\Support\Employers\Agent;

use App\Models\EmployerAgentAction;
use App\Models\EmployerInterview;
use App\Models\EmployerInterviewReport;
use App\Models\EmployerJob;
use App\Models\EmployerJobApplication;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWorkspace;
use App\Models\InterviewInvite;
use App\Models\User;
use App\Support\Employers\InterviewProcess;
use App\Support\Employers\InterviewReportDelivery;
use App\Support\Employers\ShortlistSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The questions Taurus asks the client in the chat, one step at a time, when an
 * instruction needs more than one answer:
 *
 *   "retake L1"            → which role?  → which candidates?  → "Reply YES"
 *   "send Kunal's report"  → which role?  → which candidates?  → the PDF + recording link
 *
 * It is a conversation in the chat, not a form: the client answers with a number,
 * a name, "all" or a role's title. While one is open every message answers it
 * (so "1" is never read as a new job), and CANCEL drops it. A step the client
 * already answered in the first message is skipped. Retakes are only ever
 * proposed here; nothing happens until the client says YES.
 */
final readonly class WhatsAppChoice
{
    private const TTL_MINUTES = 30;

    private const MAX_TRIES = 3;

    private const MAX_LISTED = 15;

    public function __construct(
        private HiringActionPlanner $planner,
        private InterviewReportDelivery $reports,
        private InterviewProcess $process,
    ) {}

    /**
     * Does this message start one of these conversations?
     *
     * @return array{type: string, round: string|null}|null
     */
    public static function detect(string $text): ?array
    {
        $t = self::digits(mb_strtolower(trim($text)));
        if ($t === '' || mb_strlen($t) > 200) {
            return null;
        }

        $round = null;
        if (preg_match('/\bl\s?([1-9])\b/u', $t, $m) === 1 || preg_match('/\b(?:round|rd)\s*([1-9])\b/u', $t, $m) === 1) {
            $round = 'l'.$m[1];
        } elseif (preg_match('/\b(first|1st)\s+round\b/u', $t) === 1) {
            $round = 'l1';
        } elseif (preg_match('/\b(second|2nd)\s+round\b/u', $t) === 1) {
            $round = 'l2';
        } elseif (preg_match('/\b(pehla|pehli|pahla|pahli|modalu)\s+round\b|पहला\s+राउंड|ಮೊದಲ\s+ರೌಂಡ್/u', $t) === 1) {
            $round = 'l1';
        } elseif (preg_match('/\b(doosra|doosri|dusra|dusri|eradane)\s+round\b|दूसरा\s+राउंड|ಎರಡನೇ\s+ರೌಂಡ್/u', $t) === 1) {
            $round = 'l2';
        }

        $retake = preg_match('/\b(re-?take|re-?do|re-?attempt|re-?open|re-?sit|again|dobara|dubara|phir se|fir se|ek aur baar|ek baar aur|matte|mattomme|another (?:attempt|chance|try)|one more (?:attempt|chance|try|time))\b/u', $t) === 1
            || preg_match('/(?<![\p{L}\p{M}])(दोबारा|दुबारा|फिर से|फिरसे|एक बार और|ಮತ್ತೆ|ಮರು|ಇನ್ನೊಂದು ಬಾರಿ)/u', $t) === 1;
        $retake = $retake
            && ($round !== null || preg_match('/\b(interview|round|test)\b/u', $t) === 1 || preg_match('/(?<![\p{L}\p{M}])(इंटरव्यू|राउंड|टेस्ट|ಇಂಟರ್ವ್ಯೂ|ರೌಂಡ್|ಟೆಸ್ಟ್)/u', $t) === 1);
        if ($retake) {
            return ['type' => 'retake', 'round' => $round];
        }

        $wantsFile = preg_match('/\b(report|reports|recording|recordings|recorded|video)\b/u', $t) === 1
            || preg_match('/(?<![\p{L}\p{M}])(रिपोर्ट|रिकॉर्डिंग|वीडियो|ರಿಪೋರ್ಟ್|ರೆಕಾರ್ಡಿಂಗ್|ವೀಡಿಯೋ|ವಿಡಿಯೋ)/u', $t) === 1;
        $verb = preg_match('/\b(send|share|show|get|give|need|want|forward|resend|bhejo|bhej|bhejiye|dena|dijiye|chahiye|dikhao|dikhaiye|kalisi|torisi|kodi|beku)\b/u', $t) === 1
            || preg_match('/(?<![\p{L}\p{M}])(भेजो|भेज|भेजिए|दीजिए|चाहिए|दिखाओ|दिखाइए|ಕಳುಹಿಸಿ|ತೋರಿಸಿ|ಕೊಡಿ|ಬೇಕು)/u', $t) === 1;
        if ($wantsFile && $verb) {
            return ['type' => 'report', 'round' => $round];
        }

        return null;
    }

    /**
     * Is this message a new request rather than an answer to the question Taurus just asked? While a
     * conversation is open ("whose report?") a short reply is its answer ("1", "Kunal", "all"), but a
     * new role to post, a fresh retake or report request, or a long message is not, and must never be
     * swallowed as a wrong answer.
     */
    public static function startsSomethingNew(string $text): bool
    {
        $t = trim($text);
        if (self::detect($t) !== null) {
            return true;
        }
        if (preg_match('/^[\d\s,&.\-#]+$/u', self::digits($t)) === 1) {
            return false;
        }
        if (preg_match('/\b(post|hire|hiring|vacancy|opening|openings|looking for|we need|i need|need \d|requirement|required|experience|years? of|work mode|remote|jd)\b/iu', $t) === 1) {
            return true;
        }

        return count(preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY) ?: []) > 7;
    }

    /** The conversation open in this chat, unless it has gone cold. @return array<string, mixed>|null */
    public function active(EmployerWhatsappLink $link): ?array
    {
        $choice = $link->choice;
        if (! is_array($choice) || ! isset($choice['type'], $choice['step'])) {
            return null;
        }
        $at = isset($choice['at']) ? Carbon::parse((string) $choice['at']) : null;
        if ($at === null || $at->lt(now()->subMinutes(self::TTL_MINUTES))) {
            $link->update(['choice' => null]);

            return null;
        }

        return $choice;
    }

    /**
     * @param  array{type: string, round: string|null}  $request
     */
    public function start(EmployerWorkspace $workspace, EmployerWhatsappLink $link, array $request, string $text): string
    {
        $jobs = $this->jobs($workspace);
        if ($jobs->isEmpty()) {
            return 'You have no roles yet. Tell me about one and I will post it.';
        }

        // With one role there is nothing to ask. The words said stay with the conversation, so "send Kunal's report"
        // does not ask for Kunal again.
        $named = $this->jobNamedIn($jobs, $text) ?? ($jobs->count() === 1 ? $jobs->first() : null);
        $choice = ['type' => $request['type'], 'round' => $request['round'], 'step' => 'job', 'options' => $jobs->pluck('id')->all(), 'tries' => 0, 'at' => now()->toIso8601String(), 'hint' => mb_substr($text, 0, 200)];

        if ($named !== null) {
            return $this->afterJob($workspace, $link, $choice, $named);
        }

        $link->update(['choice' => $choice]);

        return $this->askJob($jobs);
    }

    /** Every message while a conversation is open lands here. Always returns the reply. */
    public function answer(EmployerWorkspace $workspace, User $user, EmployerWhatsappLink $link, string $text): string
    {
        $choice = $this->active($link);
        if ($choice === null) {
            return 'Tell me what you would like to do.';
        }

        $said = AgentWords::normalize($text);
        if (AgentWords::isNo($text) || in_array($said, ['cancel', 'stop', 'nevermind', 'never mind', 'leave it', 'drop it'], true)) {
            $link->update(['choice' => null]);

            return 'Okay, dropped it. Nothing was changed.';
        }

        return match ($choice['step']) {
            'job' => $this->answerJob($workspace, $link, $choice, $text),
            'round' => $this->answerRound($workspace, $link, $choice, $text),
            'people' => $this->answerPeople($workspace, $user, $link, $choice, $text),
            default => $this->drop($link),
        };
    }

    /** @param  array<string, mixed>  $choice */
    private function answerJob(EmployerWorkspace $workspace, EmployerWhatsappLink $link, array $choice, string $text): string
    {
        $jobs = $this->jobs($workspace)->keyBy('id');
        $options = collect((array) $choice['options'])->map(fn ($id) => $jobs->get((int) $id))->filter()->values();

        $picked = null;
        $t = self::digits(trim($text));
        if (preg_match('/^#?\s*(\d{1,6})\.?$/', $t, $m) === 1) {
            $n = (int) $m[1];
            $picked = $options->get($n - 1) ?? $options->firstWhere('id', $n);
        }
        $picked ??= $this->jobNamedIn($options, $text);

        if ($picked === null) {
            return $this->retry($link, $choice, 'I did not get which role. '.$this->askJob($options));
        }

        return $this->afterJob($workspace, $link, $choice, $picked);
    }

    /** @param  array<string, mixed>  $choice */
    private function answerRound(EmployerWorkspace $workspace, EmployerWhatsappLink $link, array $choice, string $text): string
    {
        $job = EmployerJob::query()->where('employer_workspace_id', $workspace->id)->find((int) ($choice['job_id'] ?? 0));
        if ($job === null) {
            return $this->drop($link);
        }

        $key = self::roundIn($text);
        $keys = $this->aiRounds($job);
        if ($key === null || ! in_array($key, $keys, true)) {
            return $this->retry($link, $choice, 'Which round? Reply '.collect($keys)->map(fn ($k) => '*'.strtoupper($k).'*')->implode(' or ').'.');
        }

        $choice['round'] = $key;

        return $this->askPeople($workspace, $link, $choice, $job);
    }

    /** @param  array<string, mixed>  $choice */
    private function afterJob(EmployerWorkspace $workspace, EmployerWhatsappLink $link, array $choice, EmployerJob $job): string
    {
        $choice['job_id'] = $job->id;
        $keys = $this->aiRounds($job);

        // A report belongs to a person, not a round: no round question for it.
        if ($choice['round'] === null && $choice['type'] === 'report') {
            $choice['round'] = $keys[0] ?? 'l1';
        }

        if ($choice['round'] === null) {
            if (count($keys) > 1) {
                $choice['step'] = 'round';
                $choice['tries'] = 0;
                $choice['at'] = now()->toIso8601String();
                $link->update(['choice' => $choice]);

                return "*{$job->title}*. Which round? Reply ".collect($keys)->map(fn ($k) => '*'.strtoupper($k).'*')->implode(' or ').'.';
            }
            $choice['round'] = $keys[0] ?? 'l1';
        }

        return $this->askPeople($workspace, $link, $choice, $job);
    }

    /** @param  array<string, mixed>  $choice */
    private function askPeople(EmployerWorkspace $workspace, EmployerWhatsappLink $link, array $choice, EmployerJob $job): string
    {
        $rows = $this->people($job, (string) $choice['round'], (string) $choice['type']);
        if ($rows->isEmpty()) {
            $link->update(['choice' => null]);

            return $choice['type'] === 'retake'
                ? 'Nobody has been invited to '.strtoupper((string) $choice['round'])." for *{$job->title}* yet, so there is nothing to retake. Say _\"send the L1 invites\"_ to invite the shortlist."
                : "There is no finished interview report for *{$job->title}* yet. I send each report here the moment an interview is scored.";
        }

        // One person to choose from, or one named in the request: do it, do not ask.
        $only = $rows->count() === 1 ? $rows : $this->hinted($rows, (string) ($choice['hint'] ?? ''));
        if ($only->isNotEmpty() && $link->user !== null) {
            return $this->finish($workspace, $link->user, $link, $job, $choice, $only->pluck('application_id')->all());
        }

        $choice['step'] = 'people';
        $choice['job_id'] = $job->id;
        $choice['people'] = $rows->pluck('application_id')->all();
        $choice['tries'] = 0;
        $choice['at'] = now()->toIso8601String();
        $link->update(['choice' => $choice]);

        $lines = $rows->take(self::MAX_LISTED)->values()->map(fn (array $r, int $i): string => ($i + 1).". *{$r['name']}* — {$r['status']}")->implode("\n");
        $more = $rows->count() > self::MAX_LISTED ? "\n…and ".($rows->count() - self::MAX_LISTED).' more on the web page.' : '';
        $ask = $choice['type'] === 'retake'
            ? 'Who should retake '.strtoupper((string) $choice['round']).'? Reply the numbers (for example *1* or *1,3*), a name, or *ALL*.'
            : 'Whose report do you want? Reply the numbers (for example *1* or *1,3*), a name, or *ALL*.';

        return "*{$job->title}*\n\n{$lines}{$more}\n\n{$ask} Reply *CANCEL* to stop.";
    }

    /** @param  array<string, mixed>  $choice */
    private function answerPeople(EmployerWorkspace $workspace, User $user, EmployerWhatsappLink $link, array $choice, string $text): string
    {
        $job = EmployerJob::query()->where('employer_workspace_id', $workspace->id)->find((int) ($choice['job_id'] ?? 0));
        if ($job === null) {
            return $this->drop($link);
        }

        $rows = $this->people($job, (string) $choice['round'], (string) $choice['type'])->values();
        $picked = $this->select($rows->take(self::MAX_LISTED)->values(), $text);
        if ($picked->isEmpty()) {
            return $this->retry($link, $choice, 'I did not get who. Reply the numbers from the list (for example *1* or *1,3*), a name, or *ALL*.');
        }

        return $this->finish($workspace, $user, $link, $job, $choice, $picked->pluck('application_id')->all());
    }

    /**
     * Everything is known: send the reports, or propose the retake for a YES.
     *
     * @param  array<string, mixed>  $choice
     * @param  list<int>  $ids
     */
    private function finish(EmployerWorkspace $workspace, User $user, EmployerWhatsappLink $link, EmployerJob $job, array $choice, array $ids): string
    {
        $link->update(['choice' => null]);

        if ($choice['type'] === 'report') {
            return $this->sendReports($link, $ids);
        }

        $plan = $this->planner->retakeRound($job, (string) $choice['round'], $ids);
        if (is_string($plan)) {
            return $plan;
        }

        $action = $this->planner->propose($plan, $workspace, $user, EmployerAgentAction::CHANNEL_WHATSAPP, $link->id);

        return $action->summary."\n\nReply *YES* to go ahead, or *NO* to hold.";
    }

    /**
     * The one person the request named, even with a typo ("kunar" finds Kunal); none when it named nobody or more than one.
     *
     * @param  Collection<int, array{application_id: int, name: string, status: string}>  $rows
     * @return Collection<int, array{application_id: int, name: string, status: string}>
     */
    private function hinted(Collection $rows, string $hint): Collection
    {
        $said = array_values(array_filter(preg_split('/[^\p{L}]+/u', mb_strtolower($hint)) ?: [], fn (string $w): bool => mb_strlen($w) >= 3));
        if ($said === []) {
            return collect();
        }

        $hits = $rows->filter(function (array $row) use ($said): bool {
            foreach (preg_split('/[^\p{L}]+/u', mb_strtolower($row['name'])) ?: [] as $part) {
                if (mb_strlen($part) < 3) {
                    continue;
                }
                foreach ($said as $word) {
                    if ($word === $part || (mb_strlen($part) >= 4 && levenshtein($word, $part) <= 1)) {
                        return true;
                    }
                }
            }

            return false;
        });

        return $hits->count() === 1 ? $hits->values() : collect();
    }

    /** @param  list<int>  $applicationIds */
    private function sendReports(EmployerWhatsappLink $link, array $applicationIds): string
    {
        $sent = [];
        $missing = [];
        foreach ($applicationIds as $id) {
            $report = EmployerInterviewReport::query()
                ->whereHas('interview', fn ($q) => $q->where('employer_job_application_id', $id))
                ->latest('id')
                ->first();
            $name = (string) (EmployerJobApplication::query()->find($id)?->candidate()->withoutGlobalScopes()->value('name') ?? 'Candidate');
            if ($report === null) {
                $missing[] = $name;

                continue;
            }
            $this->reports->resend($report, $link);
            $sent[] = $name;
        }

        $parts = [];
        if ($sent !== []) {
            $parts[] = '📄 Sent the report and the recording link for '.implode(', ', $sent).'.';
        }
        if ($missing !== []) {
            $parts[] = 'No finished report yet for '.implode(', ', $missing).'.';
        }

        return implode(' ', $parts) ?: 'There was nothing to send.';
    }

    /**
     * One row per person this conversation can act on, with a plain status.
     *
     * @return Collection<int, array{application_id: int, name: string, status: string}>
     */
    private function people(EmployerJob $job, string $round, string $type): Collection
    {
        $passMark = ShortlistSettings::forJob($job)->roundPassMark;

        if ($type === 'report') {
            return EmployerInterviewReport::query()
                ->where('employer_job_id', $job->id)
                ->with(['interview.application.candidate:id,name'])
                ->latest('id')
                ->get()
                ->unique(fn (EmployerInterviewReport $r) => $r->interview?->employer_job_application_id)
                ->filter(fn (EmployerInterviewReport $r) => $r->interview !== null)
                ->map(function (EmployerInterviewReport $r): array {
                    $i = $r->interview;

                    return [
                        'application_id' => (int) $i->employer_job_application_id,
                        'name' => (string) ($i->application?->candidate?->name ?? 'Candidate'),
                        'status' => strtoupper($i->round).' '.($i->overall_score !== null ? "{$i->overall_score}/100" : 'scored').($i->graded_at !== null ? ' · '.$i->graded_at->setTimezone('Asia/Kolkata')->format('j M') : ''),
                    ];
                })
                ->values();
        }

        return InterviewInvite::query()
            ->where('employer_job_id', $job->id)
            ->where('round', $round)
            ->with(['candidate:id,name', 'application', 'interview'])
            ->orderBy('id')
            ->get()
            ->filter(fn (InterviewInvite $i) => $i->application !== null && ! $i->application->stage->isTerminal())
            ->map(fn (InterviewInvite $i): array => [
                'application_id' => (int) $i->employer_job_application_id,
                'name' => (string) ($i->candidate?->name ?? 'Candidate'),
                'status' => $this->statusOf($i, $passMark),
            ])
            ->values();
    }

    private function statusOf(InterviewInvite $invite, int $passMark): string
    {
        $interview = $invite->interview;
        $round = strtoupper($invite->round);

        if ($interview instanceof EmployerInterview && $interview->overall_score !== null) {
            $score = (int) $interview->overall_score;
            $when = $interview->graded_at !== null ? ' · '.$interview->graded_at->setTimezone('Asia/Kolkata')->format('j M') : '';

            return "{$round} {$score}/100 (".($score >= $passMark ? 'cleared' : "below the pass mark of {$passMark}").')'.$when;
        }

        return match ($invite->status) {
            InterviewInvite::SCHEDULED => 'booked'.($invite->scheduled_at !== null ? ' for '.$invite->scheduled_at->setTimezone('Asia/Kolkata')->format('j M, g:i A') : ''),
            InterviewInvite::OPEN => 'taking it now',
            InterviewInvite::DECLINED => 'declined',
            InterviewInvite::FAILED => 'invite did not go through',
            default => 'invited, has not picked a day',
        };
    }

    /**
     * "1", "1,3", "1 and 2", "2-4", "all", or names, against the numbered list.
     *
     * @param  Collection<int, array{application_id: int, name: string, status: string}>  $rows
     * @return Collection<int, array{application_id: int, name: string, status: string}>
     */
    private function select(Collection $rows, string $text): Collection
    {
        $t = self::digits(mb_strtolower(trim($text)));
        if (preg_match('/^(all|everyone|everybody|sab|sabhi|sabko|saare|sare|all of them|them all|सभी|सब|सबको|सारे|ಎಲ್ಲರೂ|ಎಲ್ಲಾ|ಎಲ್ಲರಿಗೂ)$/u', $t) === 1) {
            return $rows;
        }

        $numbers = [];
        $listy = preg_replace('/\b(?:and|aur)\b|और|ಮತ್ತು/u', ',', $t) ?? $t;
        if (preg_match('/^[\d\s,&.\-]+$/u', $listy) === 1) {
            preg_match_all('/(\d+)\s*-\s*(\d+)|(\d+)/', $listy, $m, PREG_SET_ORDER);
            foreach ($m as $hit) {
                if (($hit[1] ?? '') !== '' && ($hit[2] ?? '') !== '') {
                    foreach (range((int) $hit[1], min((int) $hit[2], (int) $hit[1] + 30)) as $n) {
                        $numbers[] = $n;
                    }
                } elseif (($hit[3] ?? '') !== '') {
                    $numbers[] = (int) $hit[3];
                }
            }
        }
        if ($numbers !== []) {
            return $rows->filter(fn ($row, int $i) => in_array($i + 1, $numbers, true))->values();
        }

        // Names, as said ("kunal", "kunal and priya").
        $said = preg_split('/\s*(?:,|&|\band\b|\baur\b|और|ಮತ್ತು)\s*/u', $t) ?: [];

        return $rows->filter(function (array $row) use ($said): bool {
            foreach ($said as $name) {
                if (trim($name) !== '' && HiringActionPlanner::nameMatches(trim($name), $row['name'])) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /** @return Collection<int, EmployerJob> */
    private function jobs(EmployerWorkspace $workspace): Collection
    {
        return EmployerJob::query()
            ->where('employer_workspace_id', $workspace->id)
            ->where('status', '!=', 'closed')
            ->latest('id')
            ->limit(8)
            ->get();
    }

    /** @param  Collection<int, EmployerJob>  $jobs */
    private function askJob(Collection $jobs): string
    {
        $counts = EmployerJobApplication::query()->whereIn('employer_job_id', $jobs->pluck('id'))->selectRaw('employer_job_id, count(*) as n')->groupBy('employer_job_id')->pluck('n', 'employer_job_id');

        $lines = $jobs->values()->map(function (EmployerJob $j, int $i) use ($counts): string {
            $n = (int) ($counts[$j->id] ?? 0);

            return ($i + 1).". *{$j->title}* · #{$j->id} · posted {$j->created_at?->format('j M')} · {$n} ".($n === 1 ? 'candidate' : 'candidates');
        })->implode("\n");

        return "Which role? Reply with the number.\n\n{$lines}\n\nReply *CANCEL* to stop.";
    }

    /**
     * The one role a message names ("#24", or its title), if exactly one.
     *
     * @param  Collection<int, EmployerJob>  $jobs
     */
    private function jobNamedIn(Collection $jobs, string $text): ?EmployerJob
    {
        $t = mb_strtolower($text);
        if (preg_match('/#\s*(\d{1,6})\b/', $t, $m) === 1) {
            $byId = $jobs->firstWhere('id', (int) $m[1]);
            if ($byId !== null) {
                return $byId;
            }
        }

        $byTitle = $jobs->filter(fn (EmployerJob $j) => mb_strlen($j->title) >= 3 && str_contains($t, mb_strtolower($j->title)))->values();

        return $byTitle->count() === 1 ? $byTitle->first() : null;
    }

    /** @return list<string> */
    private function aiRounds(EmployerJob $job): array
    {
        return $this->process->forJob($job)
            ->filter(fn ($r) => $r->enabled && $r->isPlatformRun())
            ->map(fn ($r) => (string) $r->key)
            ->values()
            ->all();
    }

    /** Devanagari and Kannada digits (१२३, ೧೨೩) read as 123. */
    private static function digits(string $t): string
    {
        return strtr($t, [
            '०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4', '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9',
            '೦' => '0', '೧' => '1', '೨' => '2', '೩' => '3', '೪' => '4', '೫' => '5', '೬' => '6', '೭' => '7', '೮' => '8', '೯' => '9',
        ]);
    }

    public static function roundIn(string $text): ?string
    {
        $t = self::digits(mb_strtolower(trim($text)));

        return preg_match('/\bl\s?([1-9])\b/u', $t, $m) === 1 || preg_match('/^\s*(?:round\s*)?([1-9])\s*$/u', $t, $m) === 1 ? 'l'.$m[1] : null;
    }

    /** @param  array<string, mixed>  $choice */
    private function retry(EmployerWhatsappLink $link, array $choice, string $reply): string
    {
        $choice['tries'] = (int) ($choice['tries'] ?? 0) + 1;
        if ($choice['tries'] >= self::MAX_TRIES) {
            $link->update(['choice' => null]);

            return 'I could not follow that, so I stopped. Nothing was changed. Say what you would like to do again whenever you are ready.';
        }
        $choice['at'] = now()->toIso8601String();
        $link->update(['choice' => $choice]);

        return $reply;
    }

    private function drop(EmployerWhatsappLink $link): string
    {
        $link->update(['choice' => null]);

        return 'That role is no longer there, so I stopped. Say what you would like to do again.';
    }
}

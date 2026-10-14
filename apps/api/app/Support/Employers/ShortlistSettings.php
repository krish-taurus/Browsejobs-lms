<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Models\EmployerJob;
use App\Models\JobShortlistEntry;

/**
 * The shortlist agent's knobs for one JD: how many people to pick, which
 * candidate sources to search, whether it runs by itself the moment the JD is
 * published, and how it decides.
 *
 * By default (require_interview) a CV match alone never gets anyone on the
 * list. First priority: CV match ≥ first_cv_bar (60) AND AI interview ≥
 * first_interview_bar (65). Second priority: CV ≥ second_cv_bar (45) AND
 * interview ≥ second_interview_bar (45). Anyone with an AI interview below
 * that is not shortlisted. Students with a CV and an AI interview always come
 * first.
 *
 * include_cv_only (default on): someone whose CV clears second_cv_bar but who
 * has no AI interview at all is still shortlisted, as third priority, and
 * their first AI interview is L1. Off: they are sent the AI screening
 * interview instead and shortlisted when they pass it.
 *
 * With auto_invite, everyone shortlisted is sent the interview invite (pick
 * a day for L1) straight away. With require_interview off, the older single
 * match score and min_score apply.
 *
 * confirm_rounds hands each step to the client instead: nobody is invited to
 * L1, and nobody who clears a round is invited to the next, until the client
 * says YES to Taurus's proposal (WhatsApp, voice or the Confirm button). It
 * wins over auto_invite. It is off by default: the agents run every step on
 * their own and tell the client as they go; a client who wants to approve each
 * round switches it on.
 *
 * interview_time is the time of day (India) of every L1/L2 slot offered on
 * WhatsApp; round_pass_mark is the score that clears L1 (→ the L2 invite)
 * and L2 (→ the client's report).
 *
 * Stored on employer_jobs.shortlist_settings; a JD that never saved any
 * gets these defaults, so publishing always runs the agent across all three
 * sources.
 */
final readonly class ShortlistSettings
{
    public const DEFAULT_TOP_N = 10;

    public const MAX_TOP_N = 100;

    public const DEFAULT_MIN_SCORE = 40;

    public const DEFAULT_FIRST_CV_BAR = 60;

    public const DEFAULT_FIRST_INTERVIEW_BAR = 65;

    public const DEFAULT_SECOND_CV_BAR = 45;

    public const DEFAULT_SECOND_INTERVIEW_BAR = 45;

    public const DEFAULT_INTERVIEW_TIME = '11:00';

    public const DEFAULT_ROUND_PASS_MARK = 60;

    /** @param  list<string>  $sources */
    public function __construct(
        public int $topN,
        public int $minScore,
        public array $sources,
        public bool $autoOnPublish,
        public bool $studentsNeedReadiness = false,
        public bool $requireInterview = true,
        public int $firstCvBar = self::DEFAULT_FIRST_CV_BAR,
        public int $firstInterviewBar = self::DEFAULT_FIRST_INTERVIEW_BAR,
        public int $secondCvBar = self::DEFAULT_SECOND_CV_BAR,
        public int $secondInterviewBar = self::DEFAULT_SECOND_INTERVIEW_BAR,
        public bool $autoInvite = true,
        public string $interviewTime = self::DEFAULT_INTERVIEW_TIME,
        public int $roundPassMark = self::DEFAULT_ROUND_PASS_MARK,
        public bool $confirmRounds = false,
        public bool $includeCvOnly = true,
    ) {}

    /** L1 invites go out by themselves only when the client has not asked to approve each round. */
    public function invitesAutomatically(): bool
    {
        return $this->autoInvite && ! $this->confirmRounds;
    }

    public static function forJob(EmployerJob $job): self
    {
        return self::fromArray(is_array($job->shortlist_settings) ? $job->shortlist_settings : []);
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $sources = isset($data['sources']) && is_array($data['sources'])
            ? array_values(array_intersect(JobShortlistEntry::SOURCES, $data['sources']))
            : JobShortlistEntry::SOURCES;

        return new self(
            topN: max(1, min(self::MAX_TOP_N, (int) ($data['top_n'] ?? self::DEFAULT_TOP_N))),
            minScore: max(0, min(100, (int) ($data['min_score'] ?? self::DEFAULT_MIN_SCORE))),
            sources: $sources,
            autoOnPublish: (bool) ($data['auto_on_publish'] ?? true),
            studentsNeedReadiness: (bool) ($data['students_need_readiness'] ?? false),
            requireInterview: (bool) ($data['require_interview'] ?? true),
            firstCvBar: $firstCv = self::pct($data['first_cv_bar'] ?? self::DEFAULT_FIRST_CV_BAR),
            firstInterviewBar: $firstInterview = self::pct($data['first_interview_bar'] ?? self::DEFAULT_FIRST_INTERVIEW_BAR),
            // The second priority can never ask for more than the first.
            secondCvBar: min($firstCv, self::pct($data['second_cv_bar'] ?? self::DEFAULT_SECOND_CV_BAR)),
            secondInterviewBar: min($firstInterview, self::pct($data['second_interview_bar'] ?? self::DEFAULT_SECOND_INTERVIEW_BAR)),
            autoInvite: (bool) ($data['auto_invite'] ?? true),
            interviewTime: is_string($data['interview_time'] ?? null) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $data['interview_time']) === 1
                ? $data['interview_time']
                : self::DEFAULT_INTERVIEW_TIME,
            roundPassMark: max(0, min(100, (int) ($data['round_pass_mark'] ?? self::DEFAULT_ROUND_PASS_MARK))),
            confirmRounds: (bool) ($data['confirm_rounds'] ?? false),
            includeCvOnly: (bool) ($data['include_cv_only'] ?? true),
        );
    }

    private static function pct(mixed $value): int
    {
        return max(0, min(100, (int) $value));
    }

    public function uses(string $source): bool
    {
        return in_array($source, $this->sources, true);
    }

    /** @return array{top_n: int, min_score: int, sources: list<string>, auto_on_publish: bool, students_need_readiness: bool, require_interview: bool, first_cv_bar: int, first_interview_bar: int, second_cv_bar: int, second_interview_bar: int, auto_invite: bool, interview_time: string, round_pass_mark: int, confirm_rounds: bool, include_cv_only: bool} */
    public function toArray(): array
    {
        return [
            'top_n' => $this->topN,
            'min_score' => $this->minScore,
            'sources' => $this->sources,
            'auto_on_publish' => $this->autoOnPublish,
            'students_need_readiness' => $this->studentsNeedReadiness,
            'require_interview' => $this->requireInterview,
            'first_cv_bar' => $this->firstCvBar,
            'first_interview_bar' => $this->firstInterviewBar,
            'second_cv_bar' => $this->secondCvBar,
            'second_interview_bar' => $this->secondInterviewBar,
            'auto_invite' => $this->autoInvite,
            'interview_time' => $this->interviewTime,
            'round_pass_mark' => $this->roundPassMark,
            'confirm_rounds' => $this->confirmRounds,
            'include_cv_only' => $this->includeCvOnly,
        ];
    }
}

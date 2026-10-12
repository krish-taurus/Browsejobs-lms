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
 * list: the CV match AND the AI interview score must both clear first_bar
 * (picked first), then second_bar. Someone whose CV clears second_bar but who
 * has no AI interview for the role is invited on WhatsApp to take one, and
 * shortlisted when they pass. With auto_invite, everyone shortlisted is sent
 * the interview invite (pick a day for L1) straight away. With
 * require_interview off, the older single match score and min_score apply.
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

    public const DEFAULT_FIRST_BAR = 75;

    public const DEFAULT_SECOND_BAR = 60;

    /** @param  list<string>  $sources */
    public function __construct(
        public int $topN,
        public int $minScore,
        public array $sources,
        public bool $autoOnPublish,
        public bool $studentsNeedReadiness = false,
        public bool $requireInterview = true,
        public int $firstBar = self::DEFAULT_FIRST_BAR,
        public int $secondBar = self::DEFAULT_SECOND_BAR,
        public bool $autoInvite = true,
    ) {}

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
            firstBar: $first = max(0, min(100, (int) ($data['first_bar'] ?? self::DEFAULT_FIRST_BAR))),
            // The second bar can never sit above the first.
            secondBar: min($first, max(0, min(100, (int) ($data['second_bar'] ?? self::DEFAULT_SECOND_BAR)))),
            autoInvite: (bool) ($data['auto_invite'] ?? true),
        );
    }

    public function uses(string $source): bool
    {
        return in_array($source, $this->sources, true);
    }

    /** @return array{top_n: int, min_score: int, sources: list<string>, auto_on_publish: bool, students_need_readiness: bool, require_interview: bool, first_bar: int, second_bar: int, auto_invite: bool} */
    public function toArray(): array
    {
        return [
            'top_n' => $this->topN,
            'min_score' => $this->minScore,
            'sources' => $this->sources,
            'auto_on_publish' => $this->autoOnPublish,
            'students_need_readiness' => $this->studentsNeedReadiness,
            'require_interview' => $this->requireInterview,
            'first_bar' => $this->firstBar,
            'second_bar' => $this->secondBar,
            'auto_invite' => $this->autoInvite,
        ];
    }
}

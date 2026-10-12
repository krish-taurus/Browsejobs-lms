<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Models\EmployerJob;
use App\Models\JobShortlistEntry;

/**
 * The shortlist agent's knobs for one JD: how many people to pick, the
 * lowest match score worth showing, which candidate sources to search, and
 * whether it runs by itself the moment the JD is published.
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

    /** @param  list<string>  $sources */
    public function __construct(
        public int $topN,
        public int $minScore,
        public array $sources,
        public bool $autoOnPublish,
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
        );
    }

    public function uses(string $source): bool
    {
        return in_array($source, $this->sources, true);
    }

    /** @return array{top_n: int, min_score: int, sources: list<string>, auto_on_publish: bool} */
    public function toArray(): array
    {
        return [
            'top_n' => $this->topN,
            'min_score' => $this->minScore,
            'sources' => $this->sources,
            'auto_on_publish' => $this->autoOnPublish,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Market;

use App\Enums\AiPurpose;
use App\Models\Course;
use App\Models\SyllabusRecommendation;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Market\CurriculumEvidence;
use App\Support\Tenancy\TenantContext;
use Throwable;

/**
 * Generates a Syllabus Recommendation Report for a course (PRD §6.21): fuse the
 * Advice-Graph evidence, then ask the model for evidence-backed add/expand/
 * deprioritise items. Fail-safe like the CV/booster actions — on any budget or
 * transport failure, or malformed JSON, it degrades to a deterministic gap
 * analysis (market-demanded skills absent from the outline). A report is ALWAYS
 * produced, never an error, and every item is grounded in the same evidence
 * bundle that is stored alongside it for the reviewer.
 */
final readonly class GenerateSyllabusRecommendation
{
    public function __construct(
        private AiGateway $gateway,
        private CurriculumEvidence $evidence,
    ) {}

    public function handle(User $actor, Course $course, string $source = SyllabusRecommendation::SOURCE_ON_DEMAND): SyllabusRecommendation
    {
        return app(TenantContext::class)->run($course->tenant, function () use ($actor, $course, $source): SyllabusRecommendation {
            $evidence = $this->evidence->forCourse($course);

            [$summary, $items, $contentSource] = $this->compose($actor, $evidence);

            return SyllabusRecommendation::query()->create([
                'tenant_id' => $course->tenant_id,
                'course_id' => $course->id,
                'status' => SyllabusRecommendation::STATUS_PENDING,
                'source' => $source,
                'content_source' => $contentSource,
                'summary' => $summary,
                'items' => $items,
                'evidence' => $evidence,
                'market_sample' => (int) $evidence['market_sample'],
                'generated_by' => $actor->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array{0: string, 1: list<array<string, mixed>>, 2: string}
     */
    private function compose(User $actor, array $evidence): array
    {
        try {
            $result = $this->gateway->complete($actor, AiPurpose::SyllabusRecommend, 'syllabus_recommend', 1, [
                'course' => (string) $evidence['course'],
                'outline' => (string) $evidence['outline'],
                'market_sample' => (string) $evidence['market_sample'],
                'market_demand' => $this->fmtDemand($evidence['market_demand']),
                'interview_topics' => $this->fmtPairs($evidence['interview_topics'], 'frequency'),
                'failure_points' => $this->fmtPairs($evidence['failure_points'], 'count'),
            ], ['max_tokens' => 1200]);

            $data = json_decode(trim($result->text), true);
            if (is_array($data) && is_array($data['items'] ?? null)) {
                $items = $this->cleanItems($data['items']);
                if ($items !== []) {
                    return [$this->cleanSummary($data['summary'] ?? ''), $items, 'ai'];
                }
            }
        } catch (Throwable) {
            // fall through to the deterministic analysis
        }

        return $this->fallback($evidence);
    }

    /**
     * Deterministic gap analysis: market-demanded skills that the outline does not
     * already name become "add" items, ordered by demand share; the top covered
     * failure points become "expand" items.
     *
     * @param  array<string, mixed>  $evidence
     * @return array{0: string, 1: list<array<string, mixed>>, 2: string}
     */
    private function fallback(array $evidence): array
    {
        $covered = $evidence['covered_terms'];
        $items = [];

        foreach ($evidence['market_demand'] as $row) {
            if (count($items) >= 8) {
                break;
            }
            $skill = (string) $row['skill'];
            if (! $this->outlineCovers($skill, $covered)) {
                $share = (int) round(((float) $row['share']) * 100);
                $items[] = [
                    'action' => 'add',
                    'topic' => $skill,
                    'rationale' => "In {$share}% of imported JDs but not found in the current outline.",
                    'priority' => $share >= 40 ? 'high' : ($share >= 20 ? 'medium' : 'low'),
                ];
            }
        }

        foreach (array_slice($evidence['failure_points'], 0, 3) as $row) {
            $topic = (string) $row['topic'];
            if ($this->outlineCovers($topic, $covered)) {
                $items[] = [
                    'action' => 'expand',
                    'topic' => $topic,
                    'rationale' => "Covered, but flagged as a candidate struggle {$row['count']} time(s) in real interviews.",
                    'priority' => 'medium',
                ];
            }
        }

        $sample = (int) $evidence['market_sample'];
        $summary = $items === []
            ? "No clear gaps: the outline already covers the top market-demanded skills across {$sample} imported JDs."
            : "The market and interview data show {$items[0]['topic']} and related skills in demand but under-covered. Review the items below; each links to its evidence.";

        return [$summary, $items, 'fallback'];
    }

    /** @param list<string> $covered */
    private function outlineCovers(string $skill, array $covered): bool
    {
        $skill = mb_strtolower(trim($skill));
        if ($skill === '') {
            return true;
        }
        // Whole-skill match, plus each significant word (so "data modeling" hits
        // "modeling" in the outline). Short tokens are ignored to avoid noise.
        if (in_array($skill, $covered, true)) {
            return true;
        }
        foreach (preg_split('/\s+/', $skill) ?: [] as $word) {
            if (mb_strlen($word) >= 4 && in_array($word, $covered, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return list<array<string, mixed>>
     */
    private function cleanItems(array $raw): array
    {
        $items = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $action = mb_strtolower(trim((string) ($item['action'] ?? '')));
            $topic = mb_strtolower(trim((string) ($item['topic'] ?? '')));
            if (! in_array($action, ['add', 'expand', 'deprioritise'], true) || $topic === '') {
                continue;
            }
            $priority = mb_strtolower(trim((string) ($item['priority'] ?? 'medium')));
            $items[] = [
                'action' => $action,
                'topic' => mb_substr($topic, 0, 80),
                'rationale' => mb_substr(trim((string) ($item['rationale'] ?? '')), 0, 400),
                'priority' => in_array($priority, ['high', 'medium', 'low'], true) ? $priority : 'medium',
            ];
            if (count($items) >= 10) {
                break;
            }
        }

        return $items;
    }

    private function cleanSummary(mixed $value): string
    {
        return mb_substr(trim((string) $value), 0, 600);
    }

    /**
     * @param  list<array{skill: string, count: int, share: float}>  $rows
     */
    private function fmtDemand(array $rows): string
    {
        if ($rows === []) {
            return 'No JD data imported yet.';
        }

        return implode("\n", array_map(
            fn ($r) => "- {$r['skill']}: ".round(((float) $r['share']) * 100)."% ({$r['count']} JDs)",
            $rows,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function fmtPairs(array $rows, string $valueKey): string
    {
        if ($rows === []) {
            return 'No data yet.';
        }

        return implode("\n", array_map(
            fn ($r) => "- {$r['topic']}: {$r[$valueKey]}",
            $rows,
        ));
    }
}

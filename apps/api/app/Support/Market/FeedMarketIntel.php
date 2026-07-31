<?php

declare(strict_types=1);

namespace App\Support\Market;

use App\Models\JobFeedItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Market figures counted from the postings we actually ingested.
 *
 * This replaces numbers a model produced. The old prompt asked for estimates
 * that were "order-of-magnitude correct" and the page badged the result as
 * live market analysis — a guess wearing the clothes of a measurement. Every
 * figure here is a count over job_feed_items, which the feed sync populates
 * from licensed and scraped sources.
 *
 * The trade is that real counting can come back thin, and the honest answer
 * to a thin sample is to say so. Below the configured floors this returns
 * `sufficient => false` and no numbers at all, because "18,000 openings" over
 * nine rows is worse than admitting we cannot tell yet.
 */
final readonly class FeedMarketIntel
{
    /**
     * @return array{
     *     tracks: array<string, array<string, mixed>>,
     *     rising: list<array<string, mixed>>,
     *     cooling: list<array<string, mixed>>,
     *     sample: int,
     *     window_days: int,
     *     sufficient: bool,
     *     generated_at: string
     * }
     */
    public function snapshot(): array
    {
        $windowDays = (int) config('market_intel.window_days', 30);
        $now = Carbon::now();
        $currentFrom = $now->copy()->subDays($windowDays);
        $priorFrom = $now->copy()->subDays($windowDays * 2);

        $current = $this->itemsSince($currentFrom);
        $prior = $this->itemsBetween($priorFrom, $currentFrom);

        $tracks = [];
        foreach ((array) config('market_intel.tracks', []) as $slug => $definition) {
            $tracks[$slug] = $this->track($slug, $definition, $current, $prior, $windowDays);
        }

        [$rising, $cooling] = $this->skillMovement($current, $prior);

        return [
            'tracks' => $tracks,
            'rising' => $rising,
            'cooling' => $cooling,
            'sample' => $current->count(),
            'window_days' => $windowDays,
            // The single flag every surface should read before printing a
            // number. False means "we do not have enough postings to say".
            'sufficient' => $current->count() >= (int) config('market_intel.min_sample', 40),
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /** @return Collection<int, JobFeedItem> */
    private function itemsSince(Carbon $from): Collection
    {
        return JobFeedItem::withoutGlobalScopes()
            ->where('posted_at', '>=', $from)
            ->get(['role_title', 'title', 'location', 'extracted_skills', 'source_kind', 'posted_at']);
    }

    /** @return Collection<int, JobFeedItem> */
    private function itemsBetween(Carbon $from, Carbon $to): Collection
    {
        return JobFeedItem::withoutGlobalScopes()
            ->where('posted_at', '>=', $from)
            ->where('posted_at', '<', $to)
            ->get(['role_title', 'title', 'extracted_skills', 'posted_at']);
    }

    /**
     * @param  array{label: string, skills: list<string>, titles: list<string>}  $definition
     * @param  Collection<int, JobFeedItem>  $current
     * @param  Collection<int, JobFeedItem>  $prior
     * @return array<string, mixed>
     */
    private function track(string $slug, array $definition, Collection $current, Collection $prior, int $windowDays): array
    {
        $matched = $current->filter(fn (JobFeedItem $i): bool => $this->matches($i, $definition));
        $before = $prior->filter(fn (JobFeedItem $i): bool => $this->matches($i, $definition));
        $total = $matched->count();

        $minimum = (int) config('market_intel.min_sample', 40);

        if ($total < $minimum) {
            return [
                'label' => $definition['label'],
                'sufficient' => false,
                'total' => $total,
                // Returned so a surface can say "we track 12 so far" rather
                // than implying the market itself is empty.
                'window_days' => $windowDays,
            ];
        }

        return [
            'label' => $definition['label'],
            'sufficient' => true,
            'total' => $total,
            'window_days' => $windowDays,
            'trend_pct' => $this->changePct($before->count(), $total),
            'cities' => $this->topShares($matched->pluck('location')->all(), 4),
            'roles' => $this->topCounts($matched->pluck('role_title')->all(), 4),
            'sources' => $this->topShares($matched->pluck('source_kind')->all(), 3),
        ];
    }

    /**
     * @param  array{skills: list<string>, titles: list<string>}  $definition
     */
    private function matches(JobFeedItem $item, array $definition): bool
    {
        $haystack = mb_strtolower(trim((string) $item->role_title.' '.(string) $item->title));

        foreach ($definition['titles'] as $title) {
            if (str_contains($haystack, $title)) {
                return true;
            }
        }

        $skills = array_map(
            static fn ($s): string => mb_strtolower((string) $s),
            is_array($item->extracted_skills) ? $item->extracted_skills : [],
        );

        return array_intersect($skills, $definition['skills']) !== [];
    }

    /**
     * Skills whose share of postings moved between the two windows.
     *
     * Share, not raw count: if the feed simply ingested more this month,
     * every skill's count rises and none of them are actually in more demand.
     *
     * @param  Collection<int, JobFeedItem>  $current
     * @param  Collection<int, JobFeedItem>  $prior
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function skillMovement(Collection $current, Collection $prior): array
    {
        $minimum = (int) config('market_intel.min_skill_sample', 25);

        if ($current->count() < $minimum || $prior->count() < $minimum) {
            return [[], []];
        }

        $now = $this->skillCounts($current);
        $then = $this->skillCounts($prior);
        $moves = [];

        foreach ($now as $skill => $count) {
            // A skill needs a real footprint in both windows before its move
            // means anything: 3 mentions becoming 6 is +100% and is noise.
            if ($count < 5 || ($then[$skill] ?? 0) < 5) {
                continue;
            }

            $nowShare = $count / $current->count();
            $thenShare = $then[$skill] / $prior->count();

            $moves[] = [
                'skill' => $skill,
                'change_pct' => $this->changePct($thenShare, $nowShare),
                'postings' => $count,
            ];
        }

        usort($moves, static fn (array $a, array $b): int => $b['change_pct'] <=> $a['change_pct']);

        $rising = array_values(array_filter($moves, static fn (array $m): bool => $m['change_pct'] > 0));
        $cooling = array_values(array_filter($moves, static fn (array $m): bool => $m['change_pct'] < 0));

        return [array_slice($rising, 0, 5), array_slice(array_reverse($cooling), 0, 5)];
    }

    /**
     * @param  Collection<int, JobFeedItem>  $items
     * @return array<string, int>
     */
    private function skillCounts(Collection $items): array
    {
        $counts = [];

        foreach ($items as $item) {
            foreach (is_array($item->extracted_skills) ? $item->extracted_skills : [] as $skill) {
                if (! is_string($skill) || trim($skill) === '') {
                    continue;
                }
                $key = mb_strtolower(trim($skill));
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private function changePct(float|int $from, float|int $to): int
    {
        if ($from <= 0) {
            return 0;
        }

        return (int) round((($to - $from) / $from) * 100);
    }

    /**
     * @param  list<mixed>  $values
     * @return list<array{name: string, share: int}>
     */
    private function topShares(array $values, int $limit): array
    {
        $clean = array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            $values,
        ), static fn (string $v): bool => $v !== '');

        if ($clean === []) {
            return [];
        }

        $counts = array_count_values($clean);
        arsort($counts);
        $total = count($clean);

        $out = [];
        foreach (array_slice($counts, 0, $limit, true) as $name => $count) {
            $out[] = ['name' => (string) $name, 'share' => (int) round($count / $total * 100)];
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $values
     * @return list<array{name: string, count: int}>
     */
    private function topCounts(array $values, int $limit): array
    {
        $clean = array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            $values,
        ), static fn (string $v): bool => $v !== '');

        if ($clean === []) {
            return [];
        }

        $counts = array_count_values($clean);
        arsort($counts);

        $out = [];
        foreach (array_slice($counts, 0, $limit, true) as $name => $count) {
            $out[] = ['name' => (string) $name, 'count' => (int) $count];
        }

        return $out;
    }
}

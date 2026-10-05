<?php

declare(strict_types=1);

namespace App\Support\JobFeed;

use App\Models\CvDocument;
use App\Models\JobFeedItem;
use App\Models\JobFeedSave;
use App\Models\User;
use App\Support\Entitlements\ActiveCareerBoost;
use App\Support\Entitlements\EntitlementService;

/**
 * Builds a student's relevance-ranked "Jobs for You" feed (PRD §6.22). Active,
 * unexpired items the student hasn't dismissed, scored by {@see RelevanceScorer},
 * ordered saved-first then by match, source priority, and freshness. Shared by
 * the student endpoint and the daily coach nudge.
 *
 * Empty until the student has a CV on file (candidate request, Aug 2026) —
 * every score here is "how well does your CV fit this JD," so with no CV
 * there is nothing real to rank; a list of 0% matches was actively
 * misleading. Capped at a flat number of results (CRM-editable) once there
 * is a CV to match against.
 *
 * The me/ route group carries no tenant context, so queries scope to the
 * student's own tenant explicitly.
 */
final class JobsForYou
{
    public function __construct(
        private readonly RelevanceScorer $scorer,
        private readonly EntitlementService $entitlements,
        private readonly ActiveCareerBoost $boost,
    ) {}

    /**
     * @param  array{min_match?: int, since_hours?: int, limit?: int}  $opts
     * @return list<array{item: JobFeedItem, match_pct: int, matched: list<string>, gap: list<string>, saved: bool}>
     */
    public function for(User $student, array $opts = []): array
    {
        if (! CvDocument::query()->where('user_id', $student->id)->exists()) {
            return [];
        }

        $minMatch = $opts['min_match'] ?? 0;
        // A Career Boost raises this cap while active (Aug 2026); an explicit
        // caller-supplied limit (the daily nudge's smaller digest) still wins.
        $limit = $opts['limit'] ?? max($this->entitlements->settings()->wider_market_job_limit, $this->boost->widerMarketLimit($student));

        $states = JobFeedSave::query()
            ->where('user_id', $student->id)
            ->pluck('state', 'job_feed_item_id');

        $query = JobFeedItem::query()
            ->where('tenant_id', $student->tenant_id)
            ->where('status', JobFeedItem::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('source:id,priority');

        if (isset($opts['since_hours'])) {
            $query->where('ingested_at', '>=', now()->subHours($opts['since_hours']));
        }

        $scored = [];
        foreach ($query->get() as $item) {
            if (($states[$item->id] ?? null) === JobFeedSave::STATE_DISMISSED) {
                continue;
            }

            $result = $this->scorer->score($student, $item);
            if ($result['match_pct'] < $minMatch) {
                continue;
            }

            $scored[] = [
                'item' => $item,
                'match_pct' => $result['match_pct'],
                'matched' => $result['matched'],
                'gap' => $result['gap'],
                'saved' => ($states[$item->id] ?? null) === JobFeedSave::STATE_SAVED,
            ];
        }

        usort($scored, function (array $a, array $b): int {
            return [$b['saved'], $b['match_pct'], $b['item']->source?->priority ?? 0, $b['item']->posted_at?->timestamp ?? 0]
                <=> [$a['saved'], $a['match_pct'], $a['item']->source?->priority ?? 0, $a['item']->posted_at?->timestamp ?? 0];
        });

        return array_slice($scored, 0, $limit);
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Candidates;

use App\Enums\AiPurpose;
use App\Enums\EntitlementFeature;
use App\Models\PlacementStory;
use App\Models\Product;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Entitlements\EntitlementService;
use App\Support\Verification\VerificationProfile;
use Throwable;

/**
 * The conversion surface on the candidate dashboard (PRD-E F12).
 *
 * Two channels, deliberately separate:
 *
 * 1. An AI-written nudge, generated from this candidate's own numbers — their
 *    score trend, their gap to the offer median, their missing checks, their
 *    credit balance. The model writes the sentence; it does not supply the
 *    facts. Every variable handed to it is measured, and the prompt forbids
 *    inventing statistics, naming other candidates, or claiming that buying
 *    something causes an outcome.
 *
 * 2. Real placement stories — consented, published rows the team entered,
 *    filtered to exclude the seeded samples. These are people who actually
 *    got hired, shown as themselves.
 *
 * The distinction matters and is worth keeping: channel 1 is our reading of
 * a candidate's situation, channel 2 is somebody else's real outcome. Mixing
 * them — having a model write a plausible success story — is the one thing
 * this class must never do, because a fabricated outcome is unfalsifiable to
 * the reader and indefensible to the person it was invented about.
 *
 * A deterministic fallback covers a transport failure, so the panel is never
 * empty and never blocks the dashboard.
 */
final readonly class MomentumNudge
{
    public function __construct(
        private User $candidate,
        private EntitlementService $entitlements,
    ) {}

    /**
     * @param  array{offer_median_mock: int, your_best_mock: int|null, sample: int, verified_share_pct: int}|null  $cohort
     * @param  list<array{date: string, score: int, role: string|null}>  $mockHistory
     * @return array{nudge: array{headline: string, body: string, cta_label: string, cta_kind: string, source: string}, stories: list<array<string, mixed>>, packages: list<array<string, mixed>>}
     */
    public function build(?array $cohort, array $mockHistory, int $liveApplications): array
    {
        $verification = new VerificationProfile($this->candidate);
        $summary = $verification->summary();
        $credits = $this->entitlements->balance($this->candidate, EntitlementFeature::VoiceMock->value);
        $scores = array_map(static fn (array $p): int => $p['score'], $mockHistory);
        $best = $scores === [] ? null : max($scores);

        $packages = $this->packages();

        return [
            'nudge' => $this->nudge($cohort, $scores, $best, $summary, $credits, $liveApplications, $packages),
            'stories' => $this->stories(),
            'packages' => $packages,
        ];
    }

    /**
     * @param  array{offer_median_mock: int, your_best_mock: int|null, sample: int, verified_share_pct: int}|null  $cohort
     * @param  list<int>  $scores
     * @param  array<string, mixed>  $summary
     * @param  list<array<string, mixed>>  $packages
     * @return array{headline: string, body: string, cta_label: string, cta_kind: string, source: string}
     */
    private function nudge(
        ?array $cohort,
        array $scores,
        ?int $best,
        array $summary,
        int $credits,
        int $liveApplications,
        array $packages,
    ): array {
        try {
            $result = app(AiGateway::class)->complete(
                $this->candidate,
                AiPurpose::Coach,
                'candidate_momentum',
                1,
                [
                    'best_mock' => $best === null ? 'none yet' : (string) $best,
                    'score_trend' => $scores === [] ? 'no graded mocks yet' : implode(' → ', $scores),
                    'offer_median' => $cohort === null ? 'unknown' : (string) $cohort['offer_median_mock'],
                    'applications_live' => (string) $liveApplications,
                    'checks_done' => (string) $summary['verified_count'],
                    'checks_total' => (string) $summary['total_count'],
                    'missing_checks' => $summary['missing_for_badge'] === []
                        ? 'none'
                        : implode(', ', $summary['missing_for_badge']),
                    'credits' => (string) $credits,
                    'offer_catalogue' => $packages === []
                        ? 'nothing on sale right now'
                        : implode(', ', array_map(
                            static fn (array $p): string => $p['name'].' (₹'.intdiv((int) $p['price_paise'], 100).')',
                            $packages,
                        )),
                ],
                ['max_tokens' => 400],
            );

            $parsed = json_decode(trim($result->text), true);

            if (is_array($parsed)
                && is_string($parsed['headline'] ?? null)
                && is_string($parsed['body'] ?? null)
                && is_string($parsed['cta_label'] ?? null)
                && in_array($parsed['cta_kind'] ?? null, ['mock', 'verify', 'apply', 'browse', 'package'], true)) {
                return [
                    'headline' => mb_substr($parsed['headline'], 0, 120),
                    'body' => mb_substr($parsed['body'], 0, 400),
                    'cta_label' => mb_substr($parsed['cta_label'], 0, 40),
                    'cta_kind' => $parsed['cta_kind'],
                    'source' => 'ai',
                ];
            }
        } catch (Throwable) {
            // Fall through — a coaching nudge is never worth a 500, and a
            // malformed model response is not worth guessing at.
        }

        return $this->fallback($cohort, $scores, $best, $summary, $credits, $liveApplications);
    }

    /**
     * Deterministic version of the same judgement, used when the model is
     * unavailable or returns something unusable. Same rules: only measured
     * numbers, no causal claims, no invented urgency.
     *
     * @param  array{offer_median_mock: int, your_best_mock: int|null, sample: int, verified_share_pct: int}|null  $cohort
     * @param  list<int>  $scores
     * @param  array<string, mixed>  $summary
     * @return array{headline: string, body: string, cta_label: string, cta_kind: string, source: string}
     */
    private function fallback(
        ?array $cohort,
        array $scores,
        ?int $best,
        array $summary,
        int $credits,
        int $liveApplications,
    ): array {
        if ($best === null) {
            return [
                'headline' => 'No graded mock yet',
                'body' => 'Employers rank you on your mock score, so until you take one there is nothing for them to sort on. It takes about twenty minutes.',
                'cta_label' => 'Take a mock',
                'cta_kind' => 'mock',
                'source' => 'fallback',
            ];
        }

        if (count($scores) >= 2 && $best > $scores[0]) {
            $gain = $best - $scores[0];

            return [
                'headline' => "Your score is up {$gain} points",
                'body' => $cohort !== null
                    ? "You went from {$scores[0]} to {$best}, against a median of {$cohort['offer_median_mock']} among candidates who reached an offer."
                    : "You went from {$scores[0]} to {$best} across your attempts. Keep going — retakes only ever replace a weaker score.",
                'cta_label' => $credits > 0 ? 'Retake a mock' : 'Browse roles',
                'cta_kind' => $credits > 0 ? 'mock' : 'browse',
                'source' => 'fallback',
            ];
        }

        if ($summary['missing_for_badge'] !== []) {
            $missing = implode(' and ', $summary['missing_for_badge']);

            return [
                'headline' => 'Your profile is not verified yet',
                'body' => "You are missing {$missing}. Verification is the one thing on your profile an employer cannot get from a CV.",
                'cta_label' => 'Verify profile',
                'cta_kind' => 'verify',
                'source' => 'fallback',
            ];
        }

        return [
            'headline' => $liveApplications > 0 ? 'You are in good shape' : 'Ready to apply',
            'body' => $liveApplications > 0
                ? "Verified, graded and {$liveApplications} live. The only lever left is applying to more roles."
                : 'Verified and graded, with nothing in flight. Applying is the only step left.',
            'cta_label' => 'Browse roles',
            'cta_kind' => 'browse',
            'source' => 'fallback',
        ];
    }

    /**
     * Real, consented placement stories. Seeded samples are excluded — a
     * demo row must never be shown to a candidate as somebody's outcome.
     *
     * @return list<array<string, mixed>>
     */
    private function stories(): array
    {
        return PlacementStory::query()
            ->where('is_published', true)
            ->where('consent', true)
            ->where('is_sample', false)
            ->orderBy('position')
            ->limit(3)
            ->get()
            ->map(fn (PlacementStory $s): array => [
                'id' => $s->id,
                'name' => $s->student_name,
                'before' => $s->before_label,
                'after' => $s->after_role,
                'company' => $s->company_name,
                'company_color' => $s->company_color,
                'rounds' => $s->rounds,
                'quote' => $s->quote,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packages(): array
    {
        return Product::query()
            ->where('active', true)
            ->whereIn('feature', [
                EntitlementFeature::VoiceMock->value,
                EntitlementFeature::Cv->value,
                EntitlementFeature::Mentor->value,
            ])
            ->orderBy('price_paise')
            ->limit(4)
            ->get(['sku', 'name', 'feature', 'price_paise', 'grant_amount'])
            ->map(fn (Product $p): array => [
                'sku' => $p->sku,
                'name' => $p->name,
                'feature' => $p->feature,
                'price_paise' => $p->price_paise,
                'grant_amount' => $p->grant_amount,
            ])
            ->values()
            ->all();
    }
}

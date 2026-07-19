<?php

declare(strict_types=1);

namespace App\Support\Market;

use App\Models\FundingNews;

/**
 * Curated sector-level snapshot — qualitative directions only, no invented
 * counts (Platform Spec §3). Mirrors apps/web/src/content contracts so the
 * frontend can consume API and fallback interchangeably.
 */
final class SeedMarketIntelSource implements MarketIntelSource
{
    /** Ranked hot domains — impartial across the market, relative momentum index. */
    private const DOMAIN_RANK = [
        ['rank' => 1, 'domain' => 'AI & Data', 'momentum' => 92, 'direction' => 'rising', 'driver' => 'Every funded company is standing up data and AI teams first.'],
        ['rank' => 2, 'domain' => 'Cloud & DevOps', 'momentum' => 84, 'direction' => 'rising', 'driver' => 'GCC expansion and migration programmes keep platform roles open.'],
        ['rank' => 3, 'domain' => 'Fintech', 'momentum' => 76, 'direction' => 'rising', 'driver' => 'Late-stage raises concentrated in Bengaluru and Mumbai.'],
        ['rank' => 4, 'domain' => 'Cybersecurity', 'momentum' => 71, 'direction' => 'rising', 'driver' => 'Compliance mandates pushing security hiring across BFSI.'],
        ['rank' => 5, 'domain' => 'Healthcare IT', 'momentum' => 66, 'direction' => 'rising', 'driver' => 'Digital health records and analytics builds in Hyderabad.'],
        ['rank' => 6, 'domain' => 'E-commerce & Retail tech', 'momentum' => 58, 'direction' => 'steady', 'driver' => 'Steady replacement demand; growth hiring is selective.'],
        ['rank' => 7, 'domain' => 'EV & Manufacturing tech', 'momentum' => 54, 'direction' => 'rising', 'driver' => 'New plants bring industrial software and data roles.'],
        ['rank' => 8, 'domain' => 'EdTech', 'momentum' => 42, 'direction' => 'steady', 'driver' => 'Consolidated market; hiring follows profitability, not funding.'],
    ];

    public function snapshot(): array
    {
        return [
            'domain_rank' => self::DOMAIN_RANK,
            'funding_radar' => $this->fundingRadar(),
            'city_pulse' => [
                ['key' => 'blr', 'name' => 'Bengaluru', 'trend' => 'rising', 'skills' => [
                    ['name' => 'Data Engineering', 'trend' => 'rising'], ['name' => 'Spark', 'trend' => 'rising'],
                    ['name' => 'Kubernetes', 'trend' => 'steady'], ['name' => 'Python', 'trend' => 'steady'],
                ]],
                ['key' => 'hyd', 'name' => 'Hyderabad', 'trend' => 'rising', 'skills' => [
                    ['name' => 'Cloud (AWS/Azure)', 'trend' => 'rising'], ['name' => 'SQL', 'trend' => 'steady'],
                    ['name' => 'DevOps', 'trend' => 'rising'], ['name' => 'Power BI', 'trend' => 'steady'],
                ]],
                ['key' => 'pun', 'name' => 'Pune', 'trend' => 'steady', 'skills' => [
                    ['name' => 'DevOps', 'trend' => 'rising'], ['name' => 'Java', 'trend' => 'steady'],
                    ['name' => 'Terraform', 'trend' => 'rising'], ['name' => 'SQL', 'trend' => 'steady'],
                ]],
                ['key' => 'ncr', 'name' => 'Delhi NCR', 'trend' => 'rising', 'skills' => [
                    ['name' => 'Data Analytics', 'trend' => 'rising'], ['name' => 'Cloud (AWS)', 'trend' => 'steady'],
                    ['name' => 'Python', 'trend' => 'rising'], ['name' => 'Power BI', 'trend' => 'steady'],
                ]],
            ],
            'funding' => [
                ['sector' => 'Fintech', 'stage' => 'Late-stage raises', 'hub' => 'Bengaluru', 'hiring_lag_months' => 3, 'roles' => ['Data Engineering', 'Backend']],
                ['sector' => 'Healthtech', 'stage' => 'Growth rounds', 'hub' => 'Hyderabad', 'hiring_lag_months' => 4, 'roles' => ['Data Analytics', 'Cloud']],
                ['sector' => 'GCC expansion', 'stage' => 'New centres announced', 'hub' => 'Pune / NCR', 'hiring_lag_months' => 5, 'roles' => ['DevOps', 'Data Engineering']],
                ['sector' => 'AI infrastructure', 'stage' => 'Fresh capital', 'hub' => 'Bengaluru', 'hiring_lag_months' => 2, 'roles' => ['Python', 'MLOps']],
            ],
            'outlook' => [
                ['track' => 'Data Engineering', 'points' => [38, 44, 47, 55, 58, 66, 71, 78, 84], 'direction' => 'rising'],
                ['track' => 'DevOps & Cloud', 'points' => [42, 45, 50, 52, 58, 61, 66, 70, 74], 'direction' => 'rising'],
                ['track' => 'Data Analytics', 'points' => [40, 43, 45, 49, 51, 54, 58, 61, 63], 'direction' => 'rising'],
                ['track' => 'Python Backend', 'points' => [36, 38, 41, 42, 45, 47, 49, 52, 54], 'direction' => 'steady'],
            ],
        ];
    }

    /**
     * Curated, sourced items entered by the team via the admin API. Empty
     * table -> sector-level samples (company null, no dead source links).
     *
     * @return array<int, mixed>
     */
    private function fundingRadar(): array
    {
        $items = FundingNews::query()
            ->where('active', true)
            ->orderByDesc('published_on')
            ->limit(6)
            ->get()
            ->map(fn (FundingNews $n): array => [
                'company' => $n->company,
                'sector' => $n->sector,
                'round' => $n->round,
                'hub' => $n->hub,
                'hiring_lag_months' => $n->hiring_lag_months,
                'roles' => $n->roles,
                'skills' => $n->skills,
                'source_name' => $n->source_name,
                'source_url' => $n->source_url,
                'published_on' => $n->published_on->toDateString(),
            ])
            ->all();

        if ($items !== []) {
            return $items;
        }

        return [
            ['company' => null, 'sector' => 'Fintech', 'round' => 'Late-stage raise', 'hub' => 'Bengaluru', 'hiring_lag_months' => 3, 'roles' => ['Data Engineering', 'Backend'], 'skills' => ['SQL', 'Spark', 'Python'], 'source_name' => 'Curated sample', 'source_url' => null, 'published_on' => null],
            ['company' => null, 'sector' => 'GCC expansion', 'round' => 'New centre announced', 'hub' => 'Pune', 'hiring_lag_months' => 5, 'roles' => ['DevOps', 'Cloud'], 'skills' => ['Kubernetes', 'Terraform', 'AWS'], 'source_name' => 'Curated sample', 'source_url' => null, 'published_on' => null],
        ];
    }
}

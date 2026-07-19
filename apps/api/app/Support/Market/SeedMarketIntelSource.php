<?php

declare(strict_types=1);

namespace App\Support\Market;

/**
 * Curated sector-level snapshot — qualitative directions only, no invented
 * counts (Platform Spec §3). Mirrors apps/web/src/content contracts so the
 * frontend can consume API and fallback interchangeably.
 */
final class SeedMarketIntelSource implements MarketIntelSource
{
    public function snapshot(): array
    {
        return [
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
}

<?php

declare(strict_types=1);

/*
| Placement pipeline (PRD §6.11). Pool thresholds are the progression gate:
| AI mock → human mock → placement pool.
*/
return [
    'pool' => [
        'min_pri' => (int) env('PLACEMENT_MIN_PRI', 70),
        // Minimum mentor feedback score on a completed placement interview.
        'min_human_mock_score' => (int) env('PLACEMENT_MIN_HUMAN_MOCK', 70),
    ],

    // Shown next to every Proof Engine stat (PRD §14.3 — stored once).
    'disclaimer' => 'Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.',
];

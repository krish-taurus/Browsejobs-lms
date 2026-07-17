<?php

declare(strict_types=1);

/*
| Rule-based student scoring (PRD §6.4). All scores are 0–100 integers. Weights +
| thresholds live here so the ScoreCalculator stays deterministic and tunable.
| Quiz/mock signals plug into mastery/PRI in later phases (P3.4/P4.x).
*/
return [
    'engagement' => [
        'recency_days' => 14,       // no activity for this many days → recency score 0
        'consistency_window' => 14, // distinct active days counted over this window
        'volume_window' => 7,       // events counted over this window
        'volume_target' => 20,      // events for a full volume score
        'weights' => ['recency' => 0.40, 'consistency' => 0.35, 'volume' => 0.25],
    ],

    'mastery' => [
        'weak_below' => 40,   // < this = weak (needs work)
        'strong_above' => 70, // > this = strong (went well)
        // Weight of a module's latest submitted quiz score vs topic-completion % —
        // applied ONLY when a submitted attempt exists (P3.4). topic keeps 1 - this.
        'quiz_weight' => 0.4,
    ],

    'pri' => [
        'weights' => ['mastery' => 0.50, 'engagement' => 0.30, 'attendance' => 0.20],
    ],

    'risk' => [
        'stalled_days' => 7,          // no completion in this many days = stalled
        'low_engagement' => 40,       // engagement below this is a red flag
        'low_attendance' => 60,       // attendance % below this is a red flag
        'placement_ready_pri' => 70,  // PRI at/above this = placement-ready
    ],

    // The nightly batch scores students enrolled/active within this many days.
    'active_days' => 60,
];

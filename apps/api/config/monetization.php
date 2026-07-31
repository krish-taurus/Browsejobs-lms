<?php

declare(strict_types=1);

/*
| Freemium & Revenue Engine defaults (PRD §6.17). All money in paise. These seed
| the per-tenant monetization_settings + product catalog; admins edit them at
| runtime via the monetization settings page.
*/
return [
    'cv' => [
        'free_grants' => 3,
        'pack_price_paise' => 9_900,   // ₹99 / 3-generation pack
        'pack_size' => 3,
    ],
    'voice_mock' => [
        // Every student's first voice/room interview finds this many free
        // sessions in the wallet (founder pricing, 2026-07): 3 free, then packs.
        'free_grants' => (int) env('MONETIZATION_VOICE_FREE', 3),
        'pack3_paise' => (int) env('MONETIZATION_VOICE_PACK3', 100_000),  // ₹1,000 / 3 sessions
        'pack6_paise' => (int) env('MONETIZATION_VOICE_PACK6', 150_000),  // ₹1,500 / 6 sessions
        // Enrolment no longer grants extra sessions — the free allowance above
        // is the single source of the freebie.
        'included_live' => (int) env('MONETIZATION_VOICE_INCLUDED_LIVE', 0),
        'included_self_paced' => (int) env('MONETIZATION_VOICE_INCLUDED_SP', 0),
    ],
    'mentor' => [
        'extra_paise' => 49_900,       // ₹499 / 3-session top-up pack
        // Every student starts with this many free 1:1 sessions — seeded into
        // their mentor wallet on first touch; top-ups extend it after that.
        'free_grants' => (int) env('MONETIZATION_MENTOR_FREE', 3),
        'pack_size' => (int) env('MONETIZATION_MENTOR_PACK', 3),
    ],
    'job_kit' => [
        // Interview Kit per job posting (ADR 0048): full question paper +
        // unlimited JD mocks for that job (the JD-tailored CV is included free
        // per PRD §6.22). Enrolled students and Career+ get kits at no charge.
        'price_paise' => 10_000,        // ₹100 / job
        'mentor_price_paise' => 29_900, // ₹299 / job — kit + a 1:1 mentor session
    ],
    // Extra credits a pack sku grants beyond its own feature (bundles).
    'pack_bonuses' => [
        'job-kit-mentor' => ['mentor' => 1],
    ],
    'career_plus' => [
        'price_paise' => 49_900,       // ₹499 / month
        'period_days' => 30,
    ],
    'self_paced' => [
        'pct_bps' => 5_000,            // 50% of the live fee
    ],
    'text_practice_enabled' => false,  // free unmetered text practice — off by default (founder)
];

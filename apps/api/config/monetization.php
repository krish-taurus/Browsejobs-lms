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
        'single_paise' => 24_900,      // ₹249 / session
        'pack_price_paise' => 59_900,  // ₹599 / 3-pack
        'pack_size' => 3,
        'included_live' => 5,          // per paid (live) course
        'included_self_paced' => 2,
    ],
    'mentor' => [
        'extra_paise' => 49_900,       // ₹499 / extra 1:1
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

<?php

declare(strict_types=1);

/*
| Freemium & Revenue Engine defaults (PRD §6.17). All money in paise. These seed
| the per-tenant monetization_settings + product catalog; admins edit them at
| runtime via the monetization settings page.
*/
return [
    /*
    | The free tier every new account starts on. Anyone may register as a
    | job seeker without enrolling in a programme, so these are what makes
    | the product usable before any purchase.
    */
    'signup' => [
        'free_mocks' => (int) env('SIGNUP_FREE_MOCKS', 2),
        'free_cvs' => (int) env('SIGNUP_FREE_CVS', 1),
    ],

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
    // Free-tier attempt caps for the three AI-interview flows, deliberately
    // separate systems (Aug 2026): a course mock's voice-room attempts are
    // capped per blueprint, an employer JD's interview is capped per job, the
    // AI Readiness Interview (general, CV-driven, not tied to a job) gets its
    // own smaller cap since it's a bigger 15-question investment — using one
    // never touches another's count, and none draw from the voice_mock
    // credit wallet above. Hard stop past the cap, no purchase path.
    // CRM-editable.
    'mock_attempts' => [
        'general_per_blueprint' => 3,
        'employer_per_job' => 3,
        'cv_readiness' => 2,
    ],
    // Free-tier ceiling on how many employer JDs a candidate can apply to.
    // CRM-editable.
    'job_applications' => [
        'free_limit' => 5,
    ],
    // "Wider Market" (scraped/external) feed on Jobs for You: shown only once
    // a candidate has a CV on file — there's nothing to match against
    // otherwise — capped at a flat number of results. CRM-editable.
    'wider_market' => [
        'job_limit' => 20,
    ],
    'mentor' => [
        'extra_paise' => 49_900,       // ₹499 / extra 1:1
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
    // Free unmetered text practice. Off by default (founder); CRM-editable.
    'text_practice_enabled' => (bool) env('TEXT_PRACTICE_ENABLED', false),
    // PRD-E F6 extension (candidate request, Aug 2026): the platform-wide bar
    // every employer JD gets a default Automation rule at, the moment it's
    // published — no employer setup required. An employer can still edit or
    // disable their job's rule from the Automation tab same as any other.
    // CRM-editable.
    'auto_shortlist' => [
        'enabled' => true,
        'min_score' => 70,          // JD interview score, 0-100
        'min_cv_match_pct' => 60,   // skill overlap between CV and this JD, 0-100
    ],
];

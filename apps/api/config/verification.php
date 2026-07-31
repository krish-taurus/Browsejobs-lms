<?php

declare(strict_types=1);

use App\Enums\VerificationKind;
use App\Support\Verification\Providers\AiDocumentProvider;
use App\Support\Verification\Providers\DigiLockerProvider;
use App\Support\Verification\Providers\EpfoProvider;
use App\Support\Verification\Providers\ManualReviewProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Provider routing
    |--------------------------------------------------------------------------
    | Which transport services each check. `manual` needs no credentials and
    | is the safe default: the check waits for an ops reviewer, and nothing is
    | ever auto-passed without a source behind it.
    |
    | These values are normally set in Admin → Settings → Background
    | verification (super-admin only) and applied over this config at boot,
    | with the env names below as a fallback. BrowseJobs owns the credentials,
    | not the employer — an employer buys the outcome of a check, never the
    | ability to run one against a candidate.
    */

    'routing' => [
        VerificationKind::Identity->value => env('VERIFY_IDENTITY_PROVIDER', 'manual'),
        VerificationKind::Education->value => env('VERIFY_EDUCATION_PROVIDER', 'manual'),
        VerificationKind::Employment->value => env('VERIFY_EMPLOYMENT_PROVIDER', 'manual'),
        VerificationKind::Documents->value => env('VERIFY_DOCUMENTS_PROVIDER', 'manual'),
    ],

    'providers' => [
        'manual' => ManualReviewProvider::class,
        'digilocker' => DigiLockerProvider::class,
        'epfo' => EpfoProvider::class,
        'ai_documents' => AiDocumentProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Badge policy
    |--------------------------------------------------------------------------
    | Which checks a candidate must hold, live, to display the verified badge.
    | Identity and employment are the pair employers actually ask about, so
    | they are the default gate; education and documents strengthen a profile
    | without being required.
    */

    'badge_requires' => [
        VerificationKind::Identity->value,
        VerificationKind::Employment->value,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validity
    |--------------------------------------------------------------------------
    | How long a successful check stands before it must be re-run. Employment
    | history goes stale fastest because it keeps accruing.
    */

    'validity_days' => [
        VerificationKind::Identity->value => 730,
        VerificationKind::Education->value => 1825,
        VerificationKind::Employment->value => 180,
        VerificationKind::Documents->value => 365,
    ],

];

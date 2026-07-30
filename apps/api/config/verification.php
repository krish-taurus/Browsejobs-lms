<?php

declare(strict_types=1);

use App\Enums\VerificationKind;
use App\Support\Verification\Providers\ManualReviewProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Provider routing
    |--------------------------------------------------------------------------
    | Which transport services each check. `manual` ships today and needs no
    | credentials. A real issuer aggregator (DigiLocker) or a PF/BGV vendor is
    | added by registering its class in `providers` and pointing the kind at
    | it here — no consumer changes. Credentials come from the environment and
    | are never committed.
    */

    'routing' => [
        VerificationKind::Identity->value => env('VERIFY_IDENTITY_PROVIDER', 'manual'),
        VerificationKind::Education->value => env('VERIFY_EDUCATION_PROVIDER', 'manual'),
        VerificationKind::Employment->value => env('VERIFY_EMPLOYMENT_PROVIDER', 'manual'),
        VerificationKind::Documents->value => env('VERIFY_DOCUMENTS_PROVIDER', 'manual'),
    ],

    'providers' => [
        'manual' => ManualReviewProvider::class,
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

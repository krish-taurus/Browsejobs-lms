<?php

declare(strict_types=1);

/*
| DPDP Act 2023 settings. Bump `consent_version` whenever the privacy policy or
| the telemetry-disclosure purposes change, so a re-consent can be required and
| each account's agreed version is on record.
*/
return [
    'consent_version' => env('DPDP_CONSENT_VERSION', 'v1'),
];

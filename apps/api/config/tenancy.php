<?php

declare(strict_types=1);

return [
    /*
    | Fallback tenant slug used by domain resolution when the request host does
    | not match any tenant's domain (e.g. single-tenant local dev on localhost).
    | Leave null in true multi-tenant environments so unknown hosts 404.
    */
    'default_slug' => env('DEFAULT_TENANT_SLUG'),
];

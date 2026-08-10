<?php

declare(strict_types=1);

/*
 * Live-class entry rules.
 */
return [
    /*
    | How long before a class starts the Join button opens. Students should not
    | be able to wander into an empty Zoom room hours early, so entry is refused
    | until this window. Late joining is always allowed. Changeable from the CRM
    | (see config/platform_settings.php) without a deploy.
    */
    'join_opens_minutes_before' => (int) env('CLASS_JOIN_OPENS_MINUTES', 1),
];

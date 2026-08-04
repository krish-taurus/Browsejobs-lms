<?php

declare(strict_types=1);

return [
    /*
    | Server-owned fee catalog (PRD §14.4). All amounts in PAISE integers —
    | never trust client-sent prices, never use floats for currency.
    | Registration ₹30,000, payable only after the free masterclass + bootcamp,
    | as a single payment or EMI 1/2/3.
    */
    'registration_paise' => (int) env('FEE_REGISTRATION_PAISE', 3_000_000),

    /** Allowed EMI instalment counts. */
    'emi_options' => [1, 2, 3],

    /** GST is inclusive in the registration price; rate in basis points (18%). */
    'gst_rate_bps' => (int) env('FEE_GST_RATE_BPS', 1800),

    'currency' => 'INR',

    /*
    | Dunning ladder (PRD §6.8), tenant-tunable later. Reminders fire the given
    | number of days BEFORE an instalment's due date (0 = due day); after due,
    | `grace_days` of daily reminders run, then a SOFT block (live classes +
    | recordings locked); `hard_block_after_days` later the SOFT block promotes
    | to a HARD block (fee screen only + counselor task). Paid → instant unblock.
    */
    'ladder' => [
        'reminder_days' => [7, 3, 1, 0],
        // Days a student may keep attending after a due date before live classes
        // and recordings lock (SOFT block). Raised from the CRM.
        'grace_days' => (int) env('FEE_GRACE_DAYS', 5),
        // Further days before the SOFT block promotes to a HARD block.
        'hard_block_after_days' => (int) env('FEE_HARD_BLOCK_AFTER_DAYS', 7),
    ],

    /*
    | Bootcamp→paid non-payer nudge ladder (PRD §5 Stage 3). Payment-link nudges
    | fire the given number of days after conversion (fee-plan creation); a
    | counselor task is raised on `counselor_task_day`. Nudges auto-pause when the
    | lead replies and stop once the first instalment is paid (member enrolled).
    */
    'conversion' => [
        'nudge_days' => [1, 3, 7],
        'counselor_task_day' => 3,
    ],
];

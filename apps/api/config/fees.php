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
        'grace_days' => 5,
        'hard_block_after_days' => 7,
    ],
];

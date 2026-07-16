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
];

<?php

declare(strict_types=1);

return [
    /*
    | Default validity window (days) for an issued voucher when the voucher
    | definition sets neither valid_days nor valid_until.
    */
    'default_valid_days' => 30,

    /*
    | Testimonial video upload constraints (PRD §5 Stage 3). Stored on the s3
    | disk under testimonials/{tenant_id}/… — the first binary upload seam.
    */
    'upload' => [
        'max_video_mb' => 50,
        'mimes' => ['mp4', 'mov', 'webm'],
    ],

    /*
    | Auto-request a testimonial (magic link) when a bootcamp completes. The
    | reward is tied to the platform testimonial only — never a Google review.
    */
    'review_request' => [
        'enabled' => true,
    ],
];

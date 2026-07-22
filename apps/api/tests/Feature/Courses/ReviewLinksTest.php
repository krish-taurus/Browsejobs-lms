<?php

declare(strict_types=1);

use App\Models\Tenant;

use function Pest\Laravel\getJson;

it('exposes the review-gate destinations and threshold for the host tenant', function () {
    Tenant::factory()->domain('acme.test')->create();

    config([
        'vouchers.review_request.min_public_rating' => 4,
        'services.social.google_review_url' => 'https://g.page/r/xyz/review',
        'services.social.instagram_url' => 'https://instagram.com/browsejobs',
        'services.social.youtube_url' => 'https://youtube.com/@browsejobs',
    ]);

    getJson('http://acme.test/api/v1/review-links')
        ->assertOk()
        ->assertJsonPath('data.min_public_rating', 4)
        ->assertJsonPath('data.google_review_url', 'https://g.page/r/xyz/review')
        ->assertJsonPath('data.instagram_url', 'https://instagram.com/browsejobs')
        ->assertJsonPath('data.youtube_url', 'https://youtube.com/@browsejobs');
});

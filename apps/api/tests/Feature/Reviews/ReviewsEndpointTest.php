<?php

declare(strict_types=1);

use App\Models\Review;
use App\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::factory()->domain('acme.test')->create();
});

function makeReview(Tenant $tenant, array $overrides = []): Review
{
    return Review::query()->create([
        'tenant_id' => $tenant->id,
        'author_name' => 'A Student',
        'rating' => 5,
        'body' => 'Great teaching.',
        'source' => 'google',
        'is_active' => true,
        ...$overrides,
    ]);
}

it('lists active reviews with pagination meta and average rating', function () {
    makeReview($this->tenant);
    makeReview($this->tenant, ['author_name' => 'B Student', 'rating' => 4, 'source' => 'whatsapp']);
    makeReview($this->tenant, ['author_name' => 'Hidden', 'is_active' => false]);

    $this->getJson('http://acme.test/api/v1/reviews')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.average_rating', 4.5);
});

it('filters by source', function () {
    makeReview($this->tenant);
    makeReview($this->tenant, ['author_name' => 'B Student', 'source' => 'whatsapp']);

    $this->getJson('http://acme.test/api/v1/reviews?source=whatsapp')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.source', 'whatsapp');
});

it('scopes reviews to the resolving tenant', function () {
    $other = Tenant::factory()->domain('other.test')->create();
    makeReview($other);

    $this->getJson('http://acme.test/api/v1/reviews')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

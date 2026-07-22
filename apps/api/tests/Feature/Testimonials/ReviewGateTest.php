<?php

declare(strict_types=1);

use App\Enums\TestimonialStatus;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('s3');
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 12121']);
});

it('keeps an under-4-star review private — never published, never rewarded', function () {
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/testimonials', [
        'stage' => 'bootcamp',
        'rating' => 3,
        'body' => 'It was okay but the pace was too fast for me at times.',
        // Even if the client sends these, a low rating must ignore them.
        'consent_publish' => true,
        'followed_instagram' => true,
        'posted_google_review' => true,
    ])->assertCreated()->assertJsonPath('data.status', 'private_feedback');

    $t = Testimonial::withoutGlobalScopes()->first();
    expect($t->status)->toBe(TestimonialStatus::PrivateFeedback)
        ->and($t->consent_publish)->toBeFalse()
        ->and($t->followed_instagram)->toBeFalse()
        ->and($t->posted_google_review)->toBeFalse();

    // Nothing reached the public wall.
    expect(Review::withoutGlobalScopes()->count())->toBe(0);
});

it('lands a 4-star-or-higher review pending with the social attestations stored', function () {
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/testimonials', [
        'stage' => 'placement',
        'rating' => 5,
        'body' => 'Placed after months of trying — the mock rounds were the difference.',
        'consent_publish' => true,
        'followed_instagram' => true,
        'subscribed_youtube' => true,
        'posted_google_review' => true,
    ])->assertCreated()->assertJsonPath('data.status', 'pending');

    $t = Testimonial::withoutGlobalScopes()->first();
    expect($t->stage)->toBe('placement')
        ->and($t->consent_publish)->toBeTrue()
        ->and($t->followed_instagram)->toBeTrue()
        ->and($t->subscribed_youtube)->toBeTrue()
        ->and($t->posted_google_review)->toBeTrue();

    // Still not on the wall until an admin confirms.
    expect(Review::withoutGlobalScopes()->count())->toBe(0);
});

it('allows one open review per funnel stage', function () {
    Sanctum::actingAs($this->student);

    $bootcamp = ['stage' => 'bootcamp', 'rating' => 5, 'body' => 'Great bootcamp, learned a lot here.'];
    $placement = ['stage' => 'placement', 'rating' => 5, 'body' => 'Landed the job, thank you team!'];

    $this->postJson('/api/v1/me/testimonials', $bootcamp)->assertCreated();
    // Same stage again is blocked…
    $this->postJson('/api/v1/me/testimonials', $bootcamp)->assertStatus(422);
    // …but a different stage is allowed.
    $this->postJson('/api/v1/me/testimonials', $placement)->assertCreated();

    expect(Testimonial::withoutGlobalScopes()->count())->toBe(2);
});

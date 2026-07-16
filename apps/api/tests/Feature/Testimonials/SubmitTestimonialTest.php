<?php

declare(strict_types=1);

use App\Enums\TestimonialStatus;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('s3');
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student', 'phone' => '+91 90000 55555']);
});

it('accepts a testimonial with an optional video and lands it pending', function () {
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/testimonials', [
        'rating' => 5,
        'body' => 'The bootcamp was direct and honest — the mock rounds felt real.',
        'consent_publish' => true,
        'video' => UploadedFile::fake()->create('story.mp4', 500, 'video/mp4'),
    ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.has_video', true);

    $testimonial = Testimonial::withoutGlobalScopes()->first();
    expect($testimonial->status)->toBe(TestimonialStatus::Pending)
        ->and($testimonial->video_path)->not->toBeNull()
        ->and(Storage::disk('s3')->exists($testimonial->video_path))->toBeTrue();
});

it('rejects an invalid video type', function () {
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/testimonials', [
        'rating' => 5,
        'body' => 'A perfectly valid body of text here.',
        'consent_publish' => true,
        'video' => UploadedFile::fake()->create('malware.exe', 500, 'application/x-msdownload'),
    ])->assertStatus(422);
});

it('allows only one open testimonial at a time', function () {
    Sanctum::actingAs($this->student);

    $payload = ['rating' => 5, 'body' => 'A perfectly valid testimonial body.', 'consent_publish' => true];

    $this->postJson('/api/v1/me/testimonials', $payload)->assertCreated();
    $this->postJson('/api/v1/me/testimonials', $payload)->assertStatus(422);

    expect(Testimonial::withoutGlobalScopes()->count())->toBe(1);
});

<?php

declare(strict_types=1);

use App\Models\AlumniCheckin;
use App\Models\CvProfile;
use App\Models\HiringPartnerFeedback;
use App\Models\JobApplication;
use App\Models\MockInterview;
use App\Models\User;
use App\Support\WhatsApp\FakeWhatsAppClient;
use App\Support\WhatsApp\WhatsAppClient;
use Database\Seeders\DemoSeeder;

beforeEach(function () {
    app()->instance(WhatsAppClient::class, new FakeWhatsAppClient);
    $this->seed(); // full DatabaseSeeder — the real thing staging runs
});

it('provisions a login-ready hero student spanning the journey', function () {
    $hero = User::withoutGlobalScopes()->where('phone', '+919000000001')->first();

    expect($hero)->not->toBeNull()
        ->and($hero->name)->toContain('demo')
        ->and($hero->telemetry_consent_at)->not->toBeNull();

    // Placement-side surfaces populated.
    expect(CvProfile::withoutGlobalScopes()->where('user_id', $hero->id)->value('data')['skills'])
        ->toContain('Python', 'SQL');
    expect(MockInterview::withoutGlobalScopes()->where('user_id', $hero->id)->where('status', 'completed')->exists())->toBeTrue();
    expect(JobApplication::withoutGlobalScopes()->where('user_id', $hero->id)->whereNotNull('job_feed_item_id')->exists())->toBeTrue();
});

it('provisions a placed alumnus with a due check-in and partner-feedback request', function () {
    $alumnus = User::withoutGlobalScopes()->where('phone', '+919000000002')->first();

    expect($alumnus)->not->toBeNull();
    expect(JobApplication::withoutGlobalScopes()->where('user_id', $alumnus->id)->where('status', 'placed')->exists())->toBeTrue();
    expect(AlumniCheckin::withoutGlobalScopes()->where('user_id', $alumnus->id)->exists())->toBeTrue();
    expect(HiringPartnerFeedback::withoutGlobalScopes()->where('status', 'requested')->exists())->toBeTrue();
});

it('is idempotent — re-seeding does not duplicate the demo personas', function () {
    $this->seed(DemoSeeder::class);

    expect(User::withoutGlobalScopes()->where('phone', '+919000000001')->count())->toBe(1)
        ->and(User::withoutGlobalScopes()->where('phone', '+919000000002')->count())->toBe(1);
});

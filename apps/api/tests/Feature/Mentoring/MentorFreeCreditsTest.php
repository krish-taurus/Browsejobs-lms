<?php

declare(strict_types=1);

use App\Enums\EntitlementFeature;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use Illuminate\Validation\ValidationException;

it('gives every student 3 free mentor sessions and charges after that', function () {
    $tenant = Tenant::factory()->create();
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);

    $service = app(EntitlementService::class);

    // Untouched wallet shows the free allowance.
    expect($service->balance($student, EntitlementFeature::Mentor->value))->toBe(3);

    // Three free bookings consume the allowance one by one.
    foreach ([2, 1, 0] as $remaining) {
        $service->consumeCredits($student, EntitlementFeature::Mentor->value, 1, 'mentor_session');
        expect($service->balance($student, EntitlementFeature::Mentor->value))->toBe($remaining);
    }

    // The fourth booking needs a paid top-up.
    expect(fn () => $service->consumeCredits($student, EntitlementFeature::Mentor->value, 1, 'mentor_session'))
        ->toThrow(ValidationException::class);

    // A purchased 3-session pack refills the wallet.
    $service->grantCredits($student, EntitlementFeature::Mentor->value, 3, 'purchase:mentor-extra');
    expect($service->balance($student, EntitlementFeature::Mentor->value))->toBe(3);
});

it('does not hand the free mentor allowance to other features', function () {
    // voice_mock has its own free allowance now — zero it so this test still
    // proves the mentor allowance specifically does not leak across features.
    config(['monetization.voice_mock.free_grants' => 0]);
    $tenant = Tenant::factory()->create();
    $student = User::factory()->for($tenant)->create(['user_type' => 'student']);

    expect(app(EntitlementService::class)->balance($student, EntitlementFeature::VoiceMock->value))->toBe(0);
});

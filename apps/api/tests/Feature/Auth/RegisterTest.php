<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Support\Otp\OtpNotifier;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    config(['sanctum.stateful' => ['acme.test']]);

    $this->codes = new ArrayObject;
    app()->instance(OtpNotifier::class, new class($this->codes) implements OtpNotifier
    {
        public function __construct(private ArrayObject $codes) {}

        public function send(string $identifier, $channel, string $code, $purpose): void
        {
            $this->codes[$identifier] = $code;
        }
    });

    $this->tenant = Tenant::factory()->domain('acme.test')->create();
});

function regPost(string $uri, array $data)
{
    return test()
        ->withHeader('Origin', 'http://acme.test')
        ->postJson("http://acme.test{$uri}", $data);
}

it('registers a new student via phone OTP, records consent, and starts a session', function () {
    regPost('/api/v1/auth/register/request', [
        'name' => 'Meena Iyer', 'phone' => '+919111111111', 'consent' => true,
    ])->assertOk()->assertJson(['status' => 'otp_sent']);

    $code = $this->codes['+919111111111'];

    regPost('/api/v1/auth/register/verify', [
        'name' => 'Meena Iyer', 'phone' => '+919111111111', 'code' => $code, 'consent' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('user.name', 'Meena Iyer')
        ->assertJsonPath('user.roles.0', 'student');

    $this->assertAuthenticated();

    $user = User::withoutGlobalScopes()->where('phone', '+919111111111')->first();
    expect($user->tenant_id)->toBe($this->tenant->id)
        ->and($user->user_type)->toBe('student')
        ->and($user->telemetry_consent_at)->not->toBeNull()   // DPDP consent stamped
        ->and($user->consent_version)->toBe('v1');
});

it('rejects registration without DPDP consent', function () {
    regPost('/api/v1/auth/register/request', [
        'name' => 'No Consent', 'phone' => '+919111111119', 'consent' => false,
    ])->assertStatus(422);

    // And OTP was never sent.
    expect(isset($this->codes['+919111111119']))->toBeFalse();
});

it('rejects registration when the phone already has an account', function () {
    User::factory()->for($this->tenant)->create(['phone' => '+919111111111']);

    regPost('/api/v1/auth/register/request', [
        'name' => 'Dup', 'phone' => '+919111111111', 'consent' => true,
    ])->assertStatus(422);
});

it('rejects a wrong registration code', function () {
    regPost('/api/v1/auth/register/request', ['name' => 'Meena', 'phone' => '+919111111112', 'consent' => true])->assertOk();

    regPost('/api/v1/auth/register/verify', [
        'name' => 'Meena', 'phone' => '+919111111112', 'code' => '000000', 'consent' => true,
    ])->assertStatus(422);

    $this->assertGuest();
});

it('supports email-channel registration', function () {
    regPost('/api/v1/auth/register/request', [
        'name' => 'Meena', 'phone' => '+919111111113', 'email' => 'meena@acme.test', 'channel' => 'email', 'consent' => true,
    ])->assertOk();

    $code = $this->codes['meena@acme.test'];

    regPost('/api/v1/auth/register/verify', [
        'name' => 'Meena', 'phone' => '+919111111113', 'email' => 'meena@acme.test',
        'channel' => 'email', 'code' => $code, 'consent' => true,
    ])->assertCreated();

    $this->assertAuthenticated();
});

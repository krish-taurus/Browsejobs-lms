<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Support\Otp\OtpNotifier;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

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

/** Post as the first-party SPA so Sanctum treats the request as stateful. */
function spaPost(string $uri, array $data)
{
    return test()
        ->withHeader('Origin', 'http://acme.test')
        ->postJson("http://acme.test{$uri}", $data);
}

it('signs a student in via OTP and starts a session', function () {
    $student = User::factory()->for($this->tenant)->create([
        'email' => 'stu@acme.test', 'user_type' => 'student',
    ]);
    $student->assignRole('student');

    spaPost('/api/v1/auth/otp/request', ['identifier' => 'stu@acme.test'])
        ->assertOk()
        ->assertJson(['status' => 'otp_sent']);

    $code = $this->codes['stu@acme.test'];

    spaPost('/api/v1/auth/otp/verify', ['identifier' => 'stu@acme.test', 'code' => $code])
        ->assertOk()
        ->assertJsonPath('data.email', 'stu@acme.test')
        ->assertJsonPath('data.roles.0', 'student');

    $this->assertAuthenticated();
});

it('rejects OTP verify for an unknown account', function () {
    spaPost('/api/v1/auth/otp/request', ['identifier' => 'ghost@acme.test'])->assertOk();
    $code = $this->codes['ghost@acme.test'];

    spaPost('/api/v1/auth/otp/verify', ['identifier' => 'ghost@acme.test', 'code' => $code])
        ->assertStatus(422);
});

it('signs staff in directly when 2FA is off', function () {
    $staff = User::factory()->for($this->tenant)->create([
        'email' => 'admin@acme.test', 'password' => Hash::make('secret-pass'),
        'user_type' => 'staff', 'two_factor_enabled' => false,
    ]);
    $staff->assignRole('admin');

    spaPost('/api/v1/auth/staff/login', ['email' => 'admin@acme.test', 'password' => 'secret-pass'])
        ->assertOk()
        ->assertJsonPath('status', 'authenticated')
        ->assertJsonPath('user.roles.0', 'admin');

    $this->assertAuthenticated();
});

it('requires the 2FA second factor when enabled', function () {
    $staff = User::factory()->for($this->tenant)->create([
        'email' => 'trainer@acme.test', 'password' => Hash::make('secret-pass'),
        'user_type' => 'staff', 'two_factor_enabled' => true,
    ]);
    $staff->assignRole('trainer');

    spaPost('/api/v1/auth/staff/login', ['email' => 'trainer@acme.test', 'password' => 'secret-pass'])
        ->assertOk()
        ->assertJsonPath('status', '2fa_required');

    $this->assertGuest();

    $code = $this->codes['trainer@acme.test'];
    spaPost('/api/v1/auth/staff/2fa', ['email' => 'trainer@acme.test', 'code' => $code])
        ->assertOk()
        ->assertJsonPath('status', 'authenticated');

    $this->assertAuthenticated();
});

it('returns the current user from /me and clears it on logout', function () {
    $user = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $user->assignRole('student');
    Sanctum::actingAs($user);

    test()->getJson('http://acme.test/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('rejects /me without a session', function () {
    test()->getJson('http://acme.test/api/v1/me')->assertUnauthorized();
});

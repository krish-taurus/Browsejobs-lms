<?php

declare(strict_types=1);

use App\Actions\Auth\CompleteStaffTwoFactor;
use App\Actions\Auth\StaffLogin;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Otp\OtpNotifier;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->codes = new ArrayObject;

    app()->instance(OtpNotifier::class, new class($this->codes) implements OtpNotifier
    {
        public function __construct(private ArrayObject $codes) {}

        public function send(string $identifier, $channel, string $code, $purpose): void
        {
            $this->codes[$identifier] = $code;
        }
    });

    $this->tenant = Tenant::factory()->create();
});

function makeStaff(Tenant $tenant, bool $twoFactor): User
{
    $user = User::factory()->for($tenant)->create([
        'email' => 'trainer@acme.test',
        'password' => Hash::make('secret-password'),
        'user_type' => 'staff',
        'two_factor_enabled' => $twoFactor,
    ]);
    $user->assignRole('trainer');

    return $user;
}

it('rejects a wrong password', function () {
    makeStaff($this->tenant, twoFactor: false);

    expect(fn () => app(StaffLogin::class)->handle($this->tenant, 'trainer@acme.test', 'wrong'))
        ->toThrow(ValidationException::class);
});

it('signs in directly when 2FA is disabled', function () {
    makeStaff($this->tenant, twoFactor: false);

    $result = app(StaffLogin::class)->handle($this->tenant, 'trainer@acme.test', 'secret-password');

    expect($result['status'])->toBe('authenticated')
        ->and($result['user']->email)->toBe('trainer@acme.test');
});

it('challenges for 2FA and completes with the emailed code', function () {
    makeStaff($this->tenant, twoFactor: true);

    $result = app(StaffLogin::class)->handle($this->tenant, 'trainer@acme.test', 'secret-password');
    expect($result['status'])->toBe('2fa_required');

    $code = $this->codes['trainer@acme.test'];
    $user = app(CompleteStaffTwoFactor::class)->handle($this->tenant, 'trainer@acme.test', $code);

    expect($user->email)->toBe('trainer@acme.test');
});

it('rejects an incorrect 2FA code', function () {
    makeStaff($this->tenant, twoFactor: true);
    app(StaffLogin::class)->handle($this->tenant, 'trainer@acme.test', 'secret-password');

    expect(fn () => app(CompleteStaffTwoFactor::class)->handle($this->tenant, 'trainer@acme.test', '000000'))
        ->toThrow(ValidationException::class);
});

it('refuses a non-staff (student) account on the staff login', function () {
    $student = User::factory()->for($this->tenant)->create([
        'email' => 'student@acme.test',
        'password' => Hash::make('secret-password'),
        'user_type' => 'student',
    ]);
    $student->assignRole('student');

    expect(fn () => app(StaffLogin::class)->handle($this->tenant, 'student@acme.test', 'secret-password'))
        ->toThrow(ValidationException::class);
});

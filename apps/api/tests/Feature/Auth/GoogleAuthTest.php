<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    config([
        'sanctum.stateful' => ['acme.test'],
        'services.google.client_id' => 'test-client',
        'services.google.client_secret' => 'test-secret',
        'services.google.redirect' => 'http://acme.test/auth/google/callback',
        'app.frontend_url' => 'http://localhost:3000',
    ]);

    $this->tenant = Tenant::factory()->domain('acme.test')->create();
});

function fakeGoogleUser(string $email, string $name): SocialiteUser
{
    $user = new SocialiteUser;
    $user->id = 'google-'.$email;
    $user->name = $name;
    $user->email = $email;

    return $user;
}

it('stores a same-site next path before the Google redirect', function () {
    $driver = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
    $driver->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($driver);

    $this->get('http://acme.test/auth/google/redirect?next=/interview')
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth');

    expect(session('auth.intended'))->toBe('/interview');
});

it('drops an off-site next path', function () {
    $driver = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
    $driver->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($driver);

    $this->get('http://acme.test/auth/google/redirect?next=https://evil.test/phish')
        ->assertRedirect();

    expect(session('auth.intended'))->toBeNull();
});

it('sends a new Google account to the stored path and does not reuse another tenant', function () {
    $other = Tenant::factory()->domain('other.test')->create();
    $foreign = User::factory()->for($other)->create([
        'email' => 'shared@example.com',
        'name' => 'Other Tenant',
        'user_type' => 'student',
    ]);

    $driver = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
    $driver->shouldReceive('user')->once()->andReturn(fakeGoogleUser('shared@example.com', 'Asha Rao'));
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($driver);

    $this->withSession(['auth.intended' => '/interview'])
        ->get('http://acme.test/auth/google/callback')
        ->assertRedirect('http://localhost:3000/interview');

    $this->assertAuthenticated();

    $local = User::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('email', 'shared@example.com')->first();
    expect($local)->not->toBeNull()
        ->and($local->id)->not->toBe($foreign->id)
        ->and($local->tenant_id)->toBe($this->tenant->id)
        ->and($local->name)->toBe('Asha Rao');

    expect(User::withoutGlobalScopes()->where('email', 'shared@example.com')->count())->toBe(2);
});

it('still lands on the dashboard when Google has no next path', function () {
    User::factory()->for($this->tenant)->create([
        'email' => 'known@acme.test',
        'user_type' => 'student',
    ]);

    $driver = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
    $driver->shouldReceive('user')->once()->andReturn(fakeGoogleUser('known@acme.test', 'Known'));
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($driver);

    $this->get('http://acme.test/auth/google/callback')
        ->assertRedirect('http://localhost:3000/dashboard');
});

<?php

declare(strict_types=1);

use App\Actions\Auth\ConsumeMagicLink;
use App\Exceptions\InvalidMagicLinkException;
use App\Models\MagicLink;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MagicLink\MagicLinkService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

it('mints a signed, action-scoped, expiring link', function () {
    $url = app(MagicLinkService::class)->create($this->tenant, 'join-class', payload: ['redirect' => '/classes/1']);

    $link = MagicLink::query()->first();

    expect($url)->toContain('/magic/')
        ->and($url)->toContain('signature=')
        ->and($link->action)->toBe('join-class')
        ->and($link->consumed_at)->toBeNull()
        ->and($link->expires_at->isFuture())->toBeTrue();
});

it('caps auth/payment actions at a 15-minute TTL', function () {
    app(MagicLinkService::class)->create($this->tenant, 'pay.instalment', ttlMinutes: 1440);

    $link = MagicLink::query()->first();

    expect($link->expires_at->diffInMinutes(now()))->toBeLessThanOrEqual(15);
});

it('consumes a valid link exactly once', function () {
    app(MagicLinkService::class)->create($this->tenant, 'join-class');
    $token = tokenFor(MagicLink::query()->first());

    $consume = app(ConsumeMagicLink::class);
    $link = $consume->handle($token);

    expect($link->consumed_at)->not->toBeNull();

    expect(fn () => $consume->handle($token))->toThrow(InvalidMagicLinkException::class);
});

it('rejects an expired link', function () {
    app(MagicLinkService::class)->create($this->tenant, 'join-class');
    $model = MagicLink::query()->first();
    $token = tokenFor($model);
    $model->update(['expires_at' => now()->subMinute()]);

    expect(fn () => app(ConsumeMagicLink::class)->handle($token))
        ->toThrow(InvalidMagicLinkException::class);
});

it('authenticates the target user and redirects on a signed request', function () {
    $user = User::factory()->for($this->tenant)->create();
    $url = app(MagicLinkService::class)->create(
        $this->tenant,
        'join-class',
        user: $user,
        payload: ['redirect' => '/dashboard'],
    );

    $this->get($url)->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('403s a tampered (unsigned) link', function () {
    app(MagicLinkService::class)->create($this->tenant, 'join-class');
    $token = tokenFor(MagicLink::query()->first());

    // Hitting the route without a valid signature must be refused.
    $this->get("/magic/{$token}")->assertForbidden();
});

/**
 * Re-mint the plaintext token for a link by brute-forcing nothing — tests know
 * the token only at creation. To assert consume behaviour we instead create the
 * row directly with a known token.
 */
function tokenFor(MagicLink $link): string
{
    $token = 'known-test-token-'.$link->id;
    $link->update(['token_hash' => hash('sha256', $token)]);

    return $token;
}

<?php

declare(strict_types=1);

use App\Models\InAppNotification;
use App\Models\MessagePreference;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

it('lists the student their own notifications with an unread count', function () {
    InAppNotification::factory()->for($this->tenant)->count(2)->create(['user_id' => $this->student->id]);
    InAppNotification::factory()->for($this->tenant)->read()->create(['user_id' => $this->student->id]);
    InAppNotification::factory()->for($this->tenant)->create(['user_id' => User::factory()->for($this->tenant)->create()->id]);

    Sanctum::actingAs($this->student);

    $this->getJson('/api/v1/me/notifications')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('unread', 2);
});

it('marks all notifications read', function () {
    InAppNotification::factory()->for($this->tenant)->count(2)->create(['user_id' => $this->student->id]);

    Sanctum::actingAs($this->student);
    $this->postJson('/api/v1/me/notifications/read', [])->assertOk();

    expect(InAppNotification::withoutGlobalScopes()->where('user_id', $this->student->id)->whereNull('read_at')->count())->toBe(0);
});

it('shows and updates the student channel preference', function () {
    Sanctum::actingAs($this->student);

    $this->getJson('/api/v1/me/message-preferences')
        ->assertOk()->assertJsonPath('data.preferred_channel', 'whatsapp')->assertJsonPath('data.marketing_opt_in', false);

    $this->putJson('/api/v1/me/message-preferences', ['preferred_channel' => 'email', 'marketing_opt_in' => true])
        ->assertOk()->assertJsonPath('data.preferred_channel', 'email')->assertJsonPath('data.marketing_opt_in', true);

    expect(MessagePreference::withoutGlobalScopes()->where('user_id', $this->student->id)->first()->marketing_opt_in)->toBeTrue();
});

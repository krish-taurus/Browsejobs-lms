<?php

declare(strict_types=1);

use App\Models\InAppNotification;
use App\Models\Message;
use App\Models\MessagePreference;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();
});

it('scopes every messaging model to its tenant', function () {
    $userA = User::factory()->for($this->tenantA)->create();
    $userB = User::factory()->for($this->tenantB)->create();

    $tplA = MessageTemplate::factory()->for($this->tenantA)->create();
    $tplB = MessageTemplate::factory()->for($this->tenantB)->create();
    $msgA = Message::factory()->for($this->tenantA)->create(['user_id' => $userA->id]);
    $msgB = Message::factory()->for($this->tenantB)->create(['user_id' => $userB->id]);
    $notifA = InAppNotification::factory()->for($this->tenantA)->create(['user_id' => $userA->id]);
    $notifB = InAppNotification::factory()->for($this->tenantB)->create(['user_id' => $userB->id]);
    $prefA = MessagePreference::factory()->for($this->tenantA)->create(['user_id' => $userA->id]);
    $prefB = MessagePreference::factory()->for($this->tenantB)->create(['user_id' => $userB->id]);

    withinTenant($this->tenantA, function () use ($tplB, $msgB, $notifB, $prefB) {
        expect(MessageTemplate::query()->find($tplB->id))->toBeNull();
        expect(Message::query()->find($msgB->id))->toBeNull();
        expect(InAppNotification::query()->find($notifB->id))->toBeNull();
        expect(MessagePreference::query()->find($prefB->id))->toBeNull();
    });

    withinTenant($this->tenantB, function () use ($tplA, $msgA, $notifA, $prefA) {
        expect(MessageTemplate::query()->find($tplA->id))->toBeNull();
        expect(Message::query()->find($msgA->id))->toBeNull();
        expect(InAppNotification::query()->find($notifA->id))->toBeNull();
        expect(MessagePreference::query()->find($prefA->id))->toBeNull();
    });
});

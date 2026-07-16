<?php

declare(strict_types=1);

use App\Actions\Telemetry\RecordActivity;
use App\Enums\ActivityType;
use App\Events\ModuleCompleted;
use App\Events\TopicCompleted;
use App\Models\ActivityEvent;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
});

it('records an activity event', function () {
    withinTenant($this->tenant, fn () => app(RecordActivity::class)->handle($this->student, ActivityType::WatchTime, null, 120));

    $event = ActivityEvent::withoutGlobalScopes()->first();
    expect($event->type)->toBe(ActivityType::WatchTime)
        ->and($event->value)->toBe(120)
        ->and($event->user_id)->toBe($this->student->id);
});

it('records topic and module completion via listeners', function () {
    $topic = Topic::factory()->for($this->tenant)->create();
    $module = Module::factory()->for($this->tenant)->create();

    TopicCompleted::dispatch($this->student, $topic);
    ModuleCompleted::dispatch($this->student, $module);

    expect(ActivityEvent::withoutGlobalScopes()->where('type', 'topic_completed')->count())->toBe(1)
        ->and(ActivityEvent::withoutGlobalScopes()->where('type', 'module_completed')->count())->toBe(1);
});

it('ingests an allowlisted client event and rejects unknown types', function () {
    Sanctum::actingAs($this->student);

    $this->postJson('/api/v1/me/activity', ['type' => 'watch_time', 'value' => 45])
        ->assertCreated();
    expect(ActivityEvent::withoutGlobalScopes()->where('type', 'watch_time')->count())->toBe(1);

    // A server-derived type (not client-reportable) is rejected.
    $this->postJson('/api/v1/me/activity', ['type' => 'topic_completed'])->assertStatus(422);
});

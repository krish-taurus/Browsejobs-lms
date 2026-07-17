<?php

declare(strict_types=1);

use App\Enums\PointsSource;
use App\Models\PointsEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Points\PointsService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create(['user_type' => 'student']);
    $this->points = app(PointsService::class);
});

it('awards points once per source key (idempotent)', function () {
    $first = $this->points->award($this->student, PointsSource::Quiz, 'quiz:1', 30, $this->tenant->id);
    $second = $this->points->award($this->student, PointsSource::Quiz, 'quiz:1', 30, $this->tenant->id);

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull()
        ->and(PointsEvent::withoutGlobalScopes()->count())->toBe(1);
});

it('clamps to the remaining daily cap, then blocks entirely', function () {
    $settings = $this->points->settingsFor($this->tenant->id);
    $settings->update(['daily_cap' => 50]);

    $this->points->award($this->student, PointsSource::Quiz, 'quiz:1', 30, $this->tenant->id);
    $clamped = $this->points->award($this->student, PointsSource::Lab, 'lab:1', 30, $this->tenant->id);
    $blocked = $this->points->award($this->student, PointsSource::Lab, 'lab:2', 30, $this->tenant->id);

    expect($clamped->points)->toBe(20)
        ->and($blocked)->toBeNull()
        ->and((int) PointsEvent::withoutGlobalScopes()->sum('points'))->toBe(50);
});

it('never awards zero or negative points', function () {
    expect($this->points->award($this->student, PointsSource::Streak, 'day:x', 0, $this->tenant->id))->toBeNull();
});

it('scopes points events to their tenant', function () {
    $other = Tenant::factory()->create();
    $otherStudent = User::factory()->for($other)->create(['user_type' => 'student']);

    $this->points->award($this->student, PointsSource::Quiz, 'quiz:1', 10, $this->tenant->id);
    $this->points->award($otherStudent, PointsSource::Quiz, 'quiz:1', 10, $other->id);

    withinTenant($this->tenant, function () {
        expect(PointsEvent::query()->count())->toBe(1)
            ->and(PointsEvent::query()->first()->user_id)->toBe($this->student->id);
    });
});

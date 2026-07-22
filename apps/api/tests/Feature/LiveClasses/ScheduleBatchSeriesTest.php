<?php

declare(strict_types=1);

use App\Actions\LiveClasses\ScheduleBatchSeries;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Queue::fake(); // no real Zoom jobs / reminder dispatches
    $this->tenant = Tenant::factory()->create();
    $this->batch = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Eng', 'slug' => 'de']);

        return $course->batches()->create(['number' => 'DE-202608-01', 'type' => BatchType::Paid->value]);
    });
    // A start date safely in the future so every generated slot is future-dated.
    $this->from = CarbonImmutable::now()->addMonth()->startOfMonth();
});

/* ---- Action ---- */

it('generates a class only on the chosen weekdays, at the fixed time', function () {
    $created = withinTenant($this->tenant, fn () => app(ScheduleBatchSeries::class)->handle(
        batch: $this->batch,
        weekdays: [1, 3, 5], // Mon / Wed / Fri
        time: '18:00',
        durationMinutes: 90,
        count: 4,
        startDate: $this->from,
    ));

    expect($created)->toHaveCount(4);
    foreach ($created as $session) {
        expect(in_array($session->scheduled_start->isoWeekday(), [1, 3, 5], true))->toBeTrue()
            ->and($session->scheduled_start->format('H:i'))->toBe('18:00')
            ->and((int) $session->scheduled_start->diffInMinutes($session->scheduled_end))->toBe(90)
            ->and($session->status)->toBe(LiveSessionStatus::Scheduled);
    }
    // Strictly increasing — no two classes on the same slot.
    $starts = array_map(fn ($s) => $s->scheduled_start->timestamp, $created);
    expect($starts)->toBe(collect($starts)->sort()->values()->all())
        ->and(array_unique($starts))->toHaveCount(4);
});

it('titles classes from the syllabus in order when map_topics is on', function () {
    withinTenant($this->tenant, function () {
        $m = Module::query()->create(['tenant_id' => $this->tenant->id, 'course_id' => $this->batch->course_id, 'name' => 'M1', 'position' => 1]);
        Topic::query()->create(['tenant_id' => $this->tenant->id, 'module_id' => $m->id, 'name' => 'Variables', 'position' => 1]);
        Topic::query()->create(['tenant_id' => $this->tenant->id, 'module_id' => $m->id, 'name' => 'Loops', 'position' => 2]);
    });

    $created = withinTenant($this->tenant, fn () => app(ScheduleBatchSeries::class)->handle(
        batch: $this->batch,
        weekdays: [1, 2, 3, 4, 5],
        time: '10:00',
        durationMinutes: 60,
        count: 3,
        startDate: $this->from,
        mapTopics: true,
        titlePrefix: 'Class',
    ));

    // First two take the syllabus topics in order; the third falls back to the prefix.
    expect($created[0]->title)->toBe('Variables')
        ->and($created[0]->topic_id)->not->toBeNull()
        ->and($created[1]->title)->toBe('Loops')
        ->and($created[2]->title)->toBe('Class 3')
        ->and($created[2]->topic_id)->toBeNull();
});

it('does not double-book a slot that already holds a class', function () {
    // A one-off class already sits on a Wednesday 18:00 in the window.
    $slot = $this->from->next(CarbonImmutable::WEDNESDAY)->setTime(18, 0);
    withinTenant($this->tenant, fn () => LiveSession::query()->create([
        'tenant_id' => $this->tenant->id, 'batch_id' => $this->batch->id, 'title' => 'Pre-existing',
        'scheduled_start' => $slot, 'status' => LiveSessionStatus::Scheduled->value,
    ]));

    withinTenant($this->tenant, fn () => app(ScheduleBatchSeries::class)->handle(
        batch: $this->batch,
        weekdays: [3], // Wednesdays only, same 18:00 time
        time: '18:00',
        durationMinutes: 60,
        count: 3,
        startDate: $this->from,
    ));

    // The taken slot still holds exactly one class; the series skipped it.
    expect(LiveSession::withoutGlobalScopes()->where('batch_id', $this->batch->id)->where('scheduled_start', $slot)->count())->toBe(1);
});

/* ---- HTTP ---- */

it('schedules a whole series over HTTP', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/batches/{$this->batch->id}/sessions/series", [
        'weekdays' => [1, 2, 3, 4, 5],
        'time' => '19:00',
        'duration_minutes' => 90,
        'count' => 5,
        'start_date' => $this->from->toDateString(),
    ])->assertCreated()->assertJsonCount(5, 'data');

    expect(LiveSession::withoutGlobalScopes()->where('batch_id', $this->batch->id)->count())->toBe(5);
});

it('requires teach-classes permission', function () {
    $this->seed(RolePermissionSeeder::class);
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');
    Sanctum::actingAs($mentor);

    $this->postJson("/api/v1/admin/batches/{$this->batch->id}/sessions/series", [
        'weekdays' => [1], 'time' => '19:00', 'duration_minutes' => 60, 'count' => 1, 'start_date' => $this->from->toDateString(),
    ])->assertForbidden();
});

it('404s a batch in another tenant', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $admin->assignRole('admin');

    $other = Tenant::factory()->create();
    $theirs = withinTenant($other, function () use ($other) {
        $course = Course::query()->create(['tenant_id' => $other->id, 'code' => 'XX', 'name' => 'X', 'slug' => 'x']);

        return $course->batches()->create(['tenant_id' => $other->id, 'number' => 'XX-1', 'type' => BatchType::Paid->value]);
    });

    Sanctum::actingAs($admin);
    $this->postJson("/api/v1/admin/batches/{$theirs->id}/sessions/series", [
        'weekdays' => [1], 'time' => '19:00', 'duration_minutes' => 60, 'count' => 1, 'start_date' => $this->from->toDateString(),
    ])->assertNotFound();
});

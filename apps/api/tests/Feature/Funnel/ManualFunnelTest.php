<?php

declare(strict_types=1);

use App\Models\Batch;
use App\Models\Course;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

it('creates NO batches from funnel:advance while auto-advance is off', function () {
    config(['funnel.auto_advance' => false]);

    $tenant = Tenant::factory()->create();

    withinTenant($tenant, function () use ($tenant): void {
        $course = Course::factory()->for($tenant)->create(['slug' => 'manual-funnel-course']);

        Lead::query()->create([
            'tenant_id' => $tenant->id, 'lead_type' => 'masterclass', 'name' => 'Waiting Lead',
            'phone' => '+919000099991', 'phone_normalized' => '919000099991',
            'course_slug' => $course->slug, 'consented_at' => now(), 'consent_version' => 'v1',
        ]);
    });

    $this->artisan('funnel:advance')->assertSuccessful();

    withinTenant($tenant, function (): void {
        expect(Batch::query()->count())->toBe(0);
    });
});

it('sets and clears the masterclass recording for a course', function () {
    $tenant = Tenant::factory()->create();
    $course = withinTenant($tenant, fn () => Course::factory()->for($tenant)->create(['slug' => 'video-course']));

    $this->artisan('course:set-masterclass-video', [
        'course' => 'video-course', 'url' => 'https://youtu.be/abc123xyz',
    ])->assertSuccessful();

    expect($course->fresh()->masterclass_video_url)->toBe('https://youtu.be/abc123xyz');

    $this->artisan('course:set-masterclass-video', [
        'course' => 'video-course', 'url' => 'clear',
    ])->assertSuccessful();

    expect($course->fresh()->masterclass_video_url)->toBeNull();
});

it('serves the simulated-live watch feed with countdown and mid-stream offset', function () {
    $tenant = Tenant::factory()->create();

    withinTenant($tenant, function () use ($tenant): void {
        Course::factory()->for($tenant)->create([
            'slug' => 'watch-course', 'masterclass_video_url' => 'https://youtu.be/abc123xyz',
        ]);
    });

    config(['funnel.masterclass_watch_time' => '20:00', 'funnel.masterclass_duration_minutes' => 90]);

    // Before the show: waiting, counting down to today 20:00.
    Carbon::setTestNow(Carbon::today()->setTime(9, 0));
    $waiting = $this->getJson('/api/v1/masterclass/watch/watch-course')->assertOk()->json();
    expect($waiting['status'])->toBe('waiting')
        ->and(Carbon::parse($waiting['starts_at'])->format('H:i'))->toBe('20:00');

    // Mid-show: live, offset = seconds since 20:00.
    Carbon::setTestNow(Carbon::today()->setTime(20, 30));
    $live = $this->getJson('/api/v1/masterclass/watch/watch-course')->assertOk()->json();
    expect($live['status'])->toBe('live')
        ->and($live['offset_seconds'])->toBe(30 * 60);

    // After the show: waiting for tomorrow's slot.
    Carbon::setTestNow(Carbon::today()->setTime(22, 0));
    $after = $this->getJson('/api/v1/masterclass/watch/watch-course')->assertOk()->json();
    expect($after['status'])->toBe('waiting')
        ->and(Carbon::parse($after['starts_at'])->isTomorrow())->toBeTrue();

    // Slugless: resolves the first course that has a recording configured.
    $default = $this->getJson('/api/v1/masterclass/watch')->assertOk()->json();
    expect($default['course']['slug'])->toBe('watch-course');

    Carbon::setTestNow();
});

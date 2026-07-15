<?php

declare(strict_types=1);

use App\Actions\LiveClasses\ScheduleLiveSession;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\Course;
use App\Models\Tenant;
use App\Support\Zoom\FakeZoomClient;
use App\Support\Zoom\ZoomClient;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->zoom = new FakeZoomClient;
    app()->instance(ZoomClient::class, $this->zoom);

    $this->tenant = Tenant::factory()->create();
    $this->batch = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Eng', 'slug' => 'de']);

        return $course->batches()->create([
            'number' => 'DE-202608-01', 'type' => BatchType::Bootcamp->value,
        ]);
    });
});

it('schedules a session and creates its Zoom meeting via a queued job', function () {
    $session = app(ScheduleLiveSession::class)->handle(
        $this->batch,
        'Intro to SQL',
        Carbon::parse('2026-08-20 18:00'),
        Carbon::parse('2026-08-20 19:30'),
    );

    $session->refresh();

    expect($session->status)->toBe(LiveSessionStatus::Scheduled)
        ->and($session->zoom_meeting_id)->not->toBeNull()
        ->and($session->zoom_join_url)->toContain('zoom.test')
        ->and($this->zoom->created)->toHaveCount(1)
        ->and($this->zoom->created[0]['duration'])->toBe(90);
});

<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Models\Batch;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Recording;
use App\Models\Tenant;
use App\Support\Zoom\FakeZoomClient;
use App\Support\Zoom\ZoomClient;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->zoom = new FakeZoomClient;
    app()->instance(ZoomClient::class, $this->zoom);

    $this->tenant = Tenant::factory()->create();

    withinTenant($this->tenant, function (): void {
        $course = Course::factory()->for($this->tenant)->create();
        $batch = Batch::factory()->for($this->tenant)->create([
            'course_id' => $course->id, 'type' => BatchType::Paid->value, 'status' => 'active',
        ]);
        $this->session = LiveSession::query()->create([
            'tenant_id' => $this->tenant->id, 'batch_id' => $batch->id, 'kind' => 'class',
            'title' => 'Recorded Class', 'scheduled_start' => now()->subHours(3),
            'status' => 'ended', 'zoom_meeting_id' => '84326576692',
        ]);
    });
});

it('registers a finished Zoom cloud recording and self-hosts the MP4', function () {
    Storage::fake('public');

    $this->zoom->recordings['84326576692'] = [
        'share_url' => 'https://zoom.us/rec/share/abc',
        'password' => 'xyz123',
        'duration' => 85,
        'recording_files' => [
            ['file_type' => 'M4A', 'status' => 'completed', 'play_url' => 'https://zoom.us/rec/play/audio'],
            ['file_type' => 'MP4', 'status' => 'completed', 'play_url' => 'https://zoom.us/rec/play/video', 'download_url' => 'https://zoom.us/rec/download/video'],
        ],
    ];

    $this->artisan('zoom:sync-recordings')->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        $recording = Recording::query()->where('live_session_id', $this->session->id)->first();

        expect($recording)->not->toBeNull()
            ->and($recording->play_url)->toBe('https://zoom.us/rec/play/video')
            ->and($recording->passcode)->toBe('xyz123')
            ->and($recording->status->value)->toBe('stored')
            // The MP4 landed on our own storage so it plays inside the portal.
            ->and($recording->storage_path)->toBe("recordings/{$this->tenant->id}/session-{$this->session->id}.mp4");

        Storage::disk('public')->assertExists($recording->storage_path);
    });

    // Second run: already stored with a local copy — no duplicate rows.
    $this->artisan('zoom:sync-recordings')->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        expect(Recording::query()->where('live_session_id', $this->session->id)->count())->toBe(1);
    });
});

it('quietly skips classes Zoom has no recording for', function () {
    // FakeZoomClient returns null for unknown meetings (Zoom 404).
    $this->artisan('zoom:sync-recordings')->assertSuccessful();

    withinTenant($this->tenant, function (): void {
        expect(Recording::query()->count())->toBe(0);
    });
});

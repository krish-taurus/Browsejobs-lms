<?php

declare(strict_types=1);

use App\Actions\LiveClasses\RegisterCompletedRecording;
use App\Enums\BatchType;
use App\Enums\LiveSessionStatus;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Recording;
use App\Models\Tenant;

/**
 * Zoom emits one file per view of the same meeting. Registering each as its own
 * recording put the same class in the student's list two or three times, with
 * copies behaving differently — one played in the portal, another opened Zoom.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->session = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Eng', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-202608-91', 'type' => BatchType::Paid->value]);

        return LiveSession::query()->create([
            'batch_id' => $batch->id,
            'title' => 'Kubernetes Basics',
            'scheduled_start' => now()->subHours(3),
            'scheduled_end' => now()->subHours(2),
            'status' => LiveSessionStatus::Ended->value,
            'reminder_token' => 'tok',
        ]);
    });
});

it('keeps one recording however many Zoom views arrive', function () {
    $register = app(RegisterCompletedRecording::class);

    withinTenant($this->tenant, function () use ($register) {
        $register->handle($this->session, 'shared_screen_with_speaker_view', 'https://zoom.test/a');
        $register->handle($this->session, 'audio_only', 'https://zoom.test/b');
        $register->handle($this->session, 'gallery_view', 'https://zoom.test/c');
    });

    expect(Recording::withoutGlobalScopes()->where('live_session_id', $this->session->id)->count())->toBe(1);
});

it('shows the class name, not Zoom\'s file-type label', function () {
    withinTenant($this->tenant, fn () => app(RegisterCompletedRecording::class)
        ->handle($this->session, 'shared_screen_with_speaker_view', 'https://zoom.test/a'));

    expect(Recording::withoutGlobalScopes()->where('live_session_id', $this->session->id)->value('title'))
        ->toBe('Kubernetes Basics');
});

it('never trades a self-hosted copy back for a Zoom link', function () {
    $register = app(RegisterCompletedRecording::class);

    withinTenant($this->tenant, function () use ($register) {
        $register->handle($this->session, 'shared_screen_with_speaker_view', 'https://zoom.test/a');

        Recording::withoutGlobalScopes()->where('live_session_id', $this->session->id)
            ->update(['storage_path' => 'recordings/1/session-x.mp4']);

        // A later view arrives after the file was self-hosted.
        $register->handle($this->session, 'audio_only', 'https://zoom.test/b');
    });

    $recording = Recording::withoutGlobalScopes()->where('live_session_id', $this->session->id)->sole();

    expect($recording->storage_path)->toBe('recordings/1/session-x.mp4')
        ->and($recording->play_url)->toBe('https://zoom.test/a');
});

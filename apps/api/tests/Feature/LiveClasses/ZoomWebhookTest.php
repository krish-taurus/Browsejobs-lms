<?php

declare(strict_types=1);

use App\Enums\BatchType;
use App\Enums\RecordingStatus;
use App\Models\Attendance;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\Recording;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Zoom\FakeZoomClient;
use App\Support\Zoom\ZoomClient;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['services.zoom.webhook_secret' => 'test-secret']);
    Storage::fake('s3');
    app()->instance(ZoomClient::class, new FakeZoomClient);

    $this->tenant = Tenant::factory()->create();
    $this->student = User::factory()->for($this->tenant)->create([
        'email' => 'stu@acme.test', 'user_type' => 'student',
    ]);

    $this->session = withinTenant($this->tenant, function () {
        $course = Course::query()->create(['code' => 'DE', 'name' => 'Data Eng', 'slug' => 'de']);
        $batch = $course->batches()->create(['number' => 'DE-202608-01', 'type' => BatchType::Paid->value]);
        BatchMember::query()->create([
            'batch_id' => $batch->id, 'user_id' => $this->student->id, 'status' => 'enrolled',
        ]);

        return LiveSession::query()->create([
            'batch_id' => $batch->id, 'title' => 'Class',
            'scheduled_start' => '2026-08-20 18:00:00', 'scheduled_end' => '2026-08-20 19:30:00',
            'zoom_meeting_id' => '900000001',
        ]);
    });
});

function postZoom(array $payload)
{
    $body = json_encode($payload);
    $timestamp = '1723140000';
    $signature = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", 'test-secret');

    return test()->call('POST', '/api/webhooks/zoom', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_ZM_REQUEST_TIMESTAMP' => $timestamp,
        'HTTP_X_ZM_SIGNATURE' => $signature,
    ], $body);
}

it('rejects an unsigned webhook', function () {
    $this->postJson('/api/webhooks/zoom', ['event' => 'ping'])->assertForbidden();
});

it('rejects a wrong signature', function () {
    test()->call('POST', '/api/webhooks/zoom', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_ZM_REQUEST_TIMESTAMP' => '1723140000',
        'HTTP_X_ZM_SIGNATURE' => 'v0=deadbeef',
    ], json_encode(['event' => 'ping']))->assertForbidden();
});

it('answers the url validation handshake', function () {
    $response = postZoom(['event' => 'endpoint.url_validation', 'payload' => ['plainToken' => 'abc123']]);

    $response->assertOk()->assertJson([
        'plainToken' => 'abc123',
        'encryptedToken' => hash_hmac('sha256', 'abc123', 'test-secret'),
    ]);
});

it('records attendance across a join and leave, computing duration and lateness', function () {
    postZoom(['event' => 'meeting.participant_joined', 'payload' => ['object' => [
        'id' => '900000001',
        'participant' => ['email' => 'stu@acme.test', 'join_time' => '2026-08-20T18:00:00Z'],
    ]]])->assertOk();

    postZoom(['event' => 'meeting.participant_left', 'payload' => ['object' => [
        'id' => '900000001',
        'participant' => ['email' => 'stu@acme.test', 'leave_time' => '2026-08-20T18:30:00Z'],
    ]]])->assertOk();

    $attendance = Attendance::withoutGlobalScopes()->first();

    expect($attendance->total_seconds)->toBe(1800)
        ->and($attendance->attended_pct)->toBe(33)
        ->and($attendance->is_late)->toBeFalse();
});

it('ignores a participant who is not a batch member', function () {
    postZoom(['event' => 'meeting.participant_joined', 'payload' => ['object' => [
        'id' => '900000001',
        'participant' => ['email' => 'stranger@acme.test', 'join_time' => '2026-08-20T18:00:00Z'],
    ]]])->assertOk();

    expect(Attendance::withoutGlobalScopes()->count())->toBe(0);
});

it('pulls a completed recording into storage', function () {
    postZoom(['event' => 'recording.completed', 'payload' => ['object' => [
        'id' => '900000001',
        'duration' => 90,
        'recording_files' => [
            ['recording_type' => 'shared_screen_with_speaker_view', 'download_url' => 'https://zoom.test/rec/1'],
        ],
    ]]])->assertOk();

    $recording = Recording::withoutGlobalScopes()->first();

    expect($recording->status)->toBe(RecordingStatus::Stored)
        ->and($recording->storage_path)->not->toBeNull();

    Storage::disk('s3')->assertExists($recording->storage_path);
});

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\LiveClasses\RecordAttendance;
use App\Actions\LiveClasses\RegisterCompletedRecording;
use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Support\Time\AppTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives verified Zoom webhooks (signature checked by middleware). Handles the
 * URL-validation handshake, participant join/leave -> attendance, and
 * recording.completed -> queued pull to storage. Unknown events are ignored.
 */
final class ZoomWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        RecordAttendance $attendance,
        RegisterCompletedRecording $recordings,
    ): JsonResponse {
        $event = (string) $request->input('event');
        $payload = (array) $request->input('payload', []);

        if ($event === 'endpoint.url_validation') {
            return $this->answerValidation($payload);
        }

        match ($event) {
            'meeting.participant_joined' => $this->onParticipant($payload, $attendance, joined: true),
            'meeting.participant_left' => $this->onParticipant($payload, $attendance, joined: false),
            'recording.completed' => $this->onRecordingCompleted($payload, $recordings),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function answerValidation(array $payload): JsonResponse
    {
        $plainToken = (string) ($payload['plainToken'] ?? '');

        return response()->json([
            'plainToken' => $plainToken,
            'encryptedToken' => hash_hmac('sha256', $plainToken, (string) config('services.zoom.webhook_secret')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onParticipant(array $payload, RecordAttendance $attendance, bool $joined): void
    {
        $object = (array) ($payload['object'] ?? []);
        $participant = (array) ($object['participant'] ?? []);
        $email = $participant['email'] ?? null;

        $session = $this->resolveSession($object);
        if ($session === null || ! is_string($email) || $email === '') {
            return;
        }

        $user = $this->resolveMember($session, $email);
        if ($user === null) {
            return;
        }

        $at = AppTime::parse($participant['join_time'] ?? $participant['leave_time'] ?? now());

        $joined
            ? $attendance->participantJoined($session, $user, $at)
            : $attendance->participantLeft($session, $user, $at);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onRecordingCompleted(array $payload, RegisterCompletedRecording $recordings): void
    {
        $object = (array) ($payload['object'] ?? []);
        $session = $this->resolveSession($object);
        if ($session === null) {
            return;
        }

        foreach ((array) ($object['recording_files'] ?? []) as $file) {
            $file = (array) $file;
            $url = $file['download_url'] ?? null;
            if (! is_string($url) || $url === '') {
                continue;
            }

            $recordings->handle(
                $session,
                (string) ($file['recording_type'] ?? 'shared_screen_with_speaker_view'),
                $url,
                isset($object['duration']) ? (int) $object['duration'] * 60 : null,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function resolveSession(array $object): ?LiveSession
    {
        $meetingId = $object['id'] ?? null;
        if ($meetingId === null) {
            return null;
        }

        return LiveSession::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('zoom_meeting_id', (string) $meetingId)
            ->first();
    }

    private function resolveMember(LiveSession $session, string $email): ?User
    {
        $user = User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $session->tenant_id)
            ->where('email', $email)
            ->first();

        if ($user === null) {
            return null;
        }

        $isMember = $session->batch->members()->where('user_id', $user->id)->exists();

        return $isMember ? $user : null;
    }
}

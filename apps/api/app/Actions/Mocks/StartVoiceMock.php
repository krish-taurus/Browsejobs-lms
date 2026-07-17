<?php

declare(strict_types=1);

namespace App\Actions\Mocks;

use App\Enums\EntitlementFeature;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use App\Support\Mocks\VoiceMockClient;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Opens a voice mock session (PRD §6.6/§6.17). One credit is consumed up
 * front (wallets are seeded per enrolment type and topped up by module
 * rewards and paid packs); if the provider cannot create the call, the
 * credit is refunded and the row removed — a student is never charged for
 * a session that did not exist. The 10-minute cap travels to the provider
 * at creation.
 */
final readonly class StartVoiceMock
{
    public function __construct(
        private EntitlementService $entitlements,
        private VoiceMockClient $voice,
    ) {}

    public function handle(User $student): MockInterview
    {
        $existing = MockInterview::query()
            ->where('user_id', $student->id)
            ->where('mode', MockInterview::MODE_VOICE)
            ->where('status', MockInterview::STATUS_IN_PROGRESS)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing; // Resume the live call — never double-charge.
        }

        $blueprint = MockBlueprint::activeFor($student);
        if ($blueprint === null) {
            throw ValidationException::withMessages([
                'mock' => 'No interview blueprint is configured for your course yet.',
            ]);
        }

        // Throws a ValidationException when the wallet is empty — the
        // controller turns that into the one-tap top-up response.
        $this->entitlements->consumeCredits($student, EntitlementFeature::VoiceMock->value, 1, 'voice_session');

        $interview = MockInterview::query()->create([
            'tenant_id' => $student->tenant_id,
            'user_id' => $student->id,
            'mock_blueprint_id' => $blueprint->id,
            'mode' => MockInterview::MODE_VOICE,
            'status' => MockInterview::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        try {
            $session = $this->voice->createSession($interview, $blueprint, [
                'max_seconds' => (int) config('mocks.voice.max_seconds', 600),
                'wrapup_seconds' => (int) config('mocks.voice.wrapup_seconds', 60),
                'system_prompt' => $this->systemPrompt($blueprint),
            ]);
        } catch (Throwable $e) {
            $interview->delete();
            $this->entitlements->grantCredits(
                $student, EntitlementFeature::VoiceMock->value, 1, 'voice_refund:failed_start',
            );

            throw ValidationException::withMessages([
                'mock' => 'Voice interviews are unavailable right now — your credit was not used.',
            ]);
        }

        $interview->update([
            'provider_session_id' => $session->sessionId,
            'join_url' => $session->joinUrl,
        ]);

        return $interview->refresh();
    }

    /** The live interviewer persona: same rules as the text prompt, plus the time cap. */
    private function systemPrompt(MockBlueprint $blueprint): string
    {
        $minutes = intdiv((int) config('mocks.voice.max_seconds', 600), 60);

        return "You are a professional technical interviewer running a spoken mock interview for the role: {$blueprint->role_title}. "
            .'You assess: '.implode(', ', $blueprint->competencies).'. '
            .'Ask one question at a time and adapt to the candidate: probe weak answers, go deeper on strong ones. '
            .'Professional and neutral — never harsh, never chummy. Realistic to Indian IT hiring interviews. '
            ."The session lasts at most {$minutes} minutes: in the final minute, wrap up gracefully — thank the candidate and close, never cut them off mid-answer. "
            .'Never promise or imply a job, timeline, or salary.';
    }
}

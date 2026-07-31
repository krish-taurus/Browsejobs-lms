<?php

declare(strict_types=1);

namespace App\Actions\Mocks;

use App\Enums\EntitlementFeature;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\MockTurn;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Opens a text-mode mock session (PRD §6.6). Gated by the monetization flag
 * text_practice_enabled (off by default per founder preference) — the first
 * consumer of that flag. The opening question is deterministic from the
 * blueprint: zero AI cost to start, the budget spends only once the candidate
 * engages. The browser interview room rides the same session but is metered:
 * `chargeVoiceCredit` spends one voice_mock session (3 free on first touch,
 * packs extend), refunded if the session cannot be created.
 */
final readonly class StartMockInterview
{
    public function __construct(private EntitlementService $entitlements) {}

    public function handle(User $student, ?int $blueprintId = null, bool $chargeVoiceCredit = false): MockInterview
    {
        abort_unless($this->entitlements->settings()->text_practice_enabled, 403, 'Text practice is not enabled.');

        $existing = MockInterview::query()
            ->where('user_id', $student->id)
            ->where('mode', MockInterview::MODE_TEXT)
            ->where('status', MockInterview::STATUS_IN_PROGRESS)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing; // Resume rather than fork parallel sessions — never re-charge.
        }

        $blueprint = $this->blueprintFor($student, $blueprintId);

        if ($chargeVoiceCredit) {
            // Throws "Not enough voice_mock credits." when the wallet is empty —
            // the controller answers 402 with the one-tap top-ups.
            $this->entitlements->consumeCredits($student, EntitlementFeature::VoiceMock->value, 1, 'room_session');
        }

        try {
            $interview = MockInterview::query()->create([
                'tenant_id' => $student->tenant_id,
                'user_id' => $student->id,
                'mock_blueprint_id' => $blueprint->id,
                'mode' => 'text',
                // Room starts are the credit-charged ones — mark them so staff
                // reporting can tell virtual-room interviews from plain text.
                'is_room' => $chargeVoiceCredit,
                'status' => MockInterview::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);

            MockTurn::query()->create([
                'tenant_id' => $student->tenant_id,
                'mock_interview_id' => $interview->id,
                'role' => MockTurn::ROLE_INTERVIEWER,
                'body' => $blueprint->opening_question,
            ]);
        } catch (Throwable $e) {
            if ($chargeVoiceCredit) {
                $this->entitlements->grantCredits($student, EntitlementFeature::VoiceMock->value, 1, 'room_refund:failed_start');
            }

            throw $e;
        }

        return $interview;
    }

    private function blueprintFor(User $student, ?int $blueprintId): MockBlueprint
    {
        // A specific skill was requested (student picked it, or a dispatch linked it):
        // honour it only if it's one of the student's available blueprints.
        if ($blueprintId !== null) {
            $chosen = MockBlueprint::availableFor($student)->firstWhere('id', $blueprintId);
            if ($chosen !== null) {
                return $chosen;
            }
        }

        $blueprint = MockBlueprint::activeFor($student);

        if ($blueprint === null) {
            throw ValidationException::withMessages([
                'mock' => 'No interview blueprint is configured for your course yet.',
            ]);
        }

        return $blueprint;
    }
}

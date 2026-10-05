<?php

declare(strict_types=1);

namespace App\Actions\Mocks;

use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\MockTurn;
use App\Models\User;
use App\Support\Entitlements\EntitlementService;
use Illuminate\Validation\ValidationException;

/**
 * Opens a text-mode mock session (PRD §6.6). Gated by the monetization flag
 * text_practice_enabled (off by default per founder preference) — the first
 * consumer of that flag. Voice mode (P4.3) adds quota consumption here.
 * The opening question is deterministic from the blueprint: zero AI cost to
 * start, the budget spends only once the candidate engages.
 *
 * $isRoom distinguishes the two things this same action powers: plain text
 * practice (unlimited) versus the spoken interview-room experience (Aug 2026
 * candidate request) — capped per blueprint, hard stop past the cap, no
 * purchase path, and entirely separate from the employer-JD interview cap in
 * {@see \App\Actions\Employers\StartEmployerJobMock}. Deliberately two
 * independent counters rather than one shared pool.
 */
final readonly class StartMockInterview
{
    public function __construct(private EntitlementService $entitlements) {}

    public function handle(User $student, ?int $blueprintId = null, bool $isRoom = false): MockInterview
    {
        abort_unless($this->entitlements->settings()->text_practice_enabled, 403, 'Text practice is not enabled.');

        $existing = MockInterview::query()
            ->where('user_id', $student->id)
            ->where('mode', MockInterview::MODE_TEXT)
            ->where('is_room', $isRoom)
            ->where('status', MockInterview::STATUS_IN_PROGRESS)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing; // Resume rather than fork parallel sessions.
        }

        $blueprint = $this->blueprintFor($student, $blueprintId);

        if ($isRoom) {
            $limit = $this->entitlements->settings()->general_mock_attempts_per_blueprint;
            $used = MockInterview::query()
                ->where('user_id', $student->id)
                ->where('mock_blueprint_id', $blueprint->id)
                ->where('is_room', true)
                ->count();

            if ($used >= $limit) {
                throw ValidationException::withMessages([
                    'attempts' => "You've used all {$limit} interview attempts for this mock.",
                ]);
            }
        }

        $interview = MockInterview::query()->create([
            'tenant_id' => $student->tenant_id,
            'user_id' => $student->id,
            'mock_blueprint_id' => $blueprint->id,
            'mode' => 'text',
            'is_room' => $isRoom,
            'status' => MockInterview::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        MockTurn::query()->create([
            'tenant_id' => $student->tenant_id,
            'mock_interview_id' => $interview->id,
            'role' => MockTurn::ROLE_INTERVIEWER,
            'body' => $blueprint->opening_question,
        ]);

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

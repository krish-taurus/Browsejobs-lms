<?php

declare(strict_types=1);

namespace App\Actions\Mocks;

use App\Models\JobFeedItem;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\MockTurn;
use App\Models\User;

/**
 * "Quick mock for this job" (ADR 0048): spins a text mock scoped to one
 * posting's JD. The blueprint is created once per posting and hidden
 * (is_active=false) so course pickers never list it — role and competencies
 * come from the posting itself, so the interviewer stays on that JD. The rest
 * of the mock engine (answer/finish/scorecard → PRI blend) is unchanged.
 * Authorisation is the Interview Kit (enrolled / Career+ / paid unlock),
 * enforced by the controller — not the global text-practice flag, which only
 * governs free unmetered course practice.
 */
final readonly class StartJobMock
{
    public function handle(User $student, JobFeedItem $item): MockInterview
    {
        // One text mock at a time, same as the course flow — resume, don't fork.
        $existing = MockInterview::query()
            ->where('user_id', $student->id)
            ->where('mode', MockInterview::MODE_TEXT)
            ->where('status', MockInterview::STATUS_IN_PROGRESS)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $blueprint = $this->blueprintFor($item);

        $interview = MockInterview::query()->create([
            'tenant_id' => $student->tenant_id,
            'user_id' => $student->id,
            'mock_blueprint_id' => $blueprint->id,
            'mode' => 'text',
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

    private function blueprintFor(JobFeedItem $item): MockBlueprint
    {
        $role = mb_substr((string) ($item->role_title ?? $item->title), 0, 255);

        return MockBlueprint::query()->firstOrCreate(
            ['tenant_id' => $item->tenant_id, 'job_feed_item_id' => $item->id],
            [
                'role_title' => $role,
                'skill' => null,
                'competencies' => array_slice($item->extracted_skills ?? [], 0, 6) ?: ['role fit', 'communication'],
                'opening_question' => mb_substr(
                    "You're interviewing for {$role} at {$item->company}. Start with a quick introduction — then tell me why you're a fit for this specific role.",
                    0,
                    500,
                ),
                'is_active' => false, // Hidden from course blueprint pickers.
            ],
        );
    }
}

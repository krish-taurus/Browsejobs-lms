<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\EmployerInterviewStatus;
use App\Models\EmployerInterview;
use App\Models\InterviewInvite;
use App\Models\MockBlueprint;
use App\Models\MockInterview;
use App\Models\MockTurn;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * The candidate opened their interview link and pressed Start: the round is
 * taken in the voice interview room — questions spoken, answers transcribed,
 * the whole session recorded and scored — exactly like the JD interview.
 *
 * One hidden blueprint per round (employer_job_round_id, never
 * employer_job_id, so it cannot stand in for the JD mock a candidate needs to
 * apply). One attempt: an interview already in progress is resumed; a
 * finished one is not restarted.
 */
final readonly class StartRoundInterview
{
    public function handle(InterviewInvite $invite, User $candidate): MockInterview
    {
        if ($invite->candidate_user_id !== $candidate->id) {
            throw ValidationException::withMessages([
                'invite' => 'This interview link belongs to someone else. Log in with the WhatsApp number it was sent to.',
            ]);
        }

        return app(TenantContext::class)->run($candidate->tenant, function () use ($invite, $candidate): MockInterview {
            $interview = $invite->employer_interview_id !== null ? EmployerInterview::query()->find($invite->employer_interview_id) : null;

            if ($interview === null) {
                $when = $invite->chosen_date !== null ? ' It opens on '.$invite->chosen_date->format('D j M').'.' : '';
                throw ValidationException::withMessages(['invite' => 'Your interview is not open yet.'.$when.' You will get a WhatsApp message with the link that day.']);
            }

            if ($interview->mock_interview_id !== null && ($mock = MockInterview::query()->find($interview->mock_interview_id)) !== null) {
                if ($mock->status === MockInterview::STATUS_IN_PROGRESS) {
                    return $mock;
                }
                throw ValidationException::withMessages(['invite' => 'You have already taken this interview. The company will be in touch.']);
            }

            if (in_array($interview->status, [EmployerInterviewStatus::Graded, EmployerInterviewStatus::Submitted], true)) {
                throw ValidationException::withMessages(['invite' => 'You have already taken this interview. The company will be in touch.']);
            }
            if ($interview->status === EmployerInterviewStatus::Expired || ($interview->expires_at !== null && $interview->expires_at->isPast())) {
                throw ValidationException::withMessages(['invite' => 'This interview link has expired. Reply on WhatsApp and we will ask the company to reopen it.']);
            }

            $blueprint = $this->blueprintFor($interview);

            $mock = MockInterview::query()->create([
                'tenant_id' => $candidate->tenant_id,
                'user_id' => $candidate->id,
                'mock_blueprint_id' => $blueprint->id,
                'mode' => MockInterview::MODE_TEXT,
                'is_room' => true,
                'status' => MockInterview::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);

            MockTurn::query()->create([
                'tenant_id' => $candidate->tenant_id,
                'mock_interview_id' => $mock->id,
                'role' => MockTurn::ROLE_INTERVIEWER,
                'body' => $blueprint->opening_question,
            ]);

            $interview->update([
                'mock_interview_id' => $mock->id,
                'status' => EmployerInterviewStatus::InProgress->value,
                'started_at' => $interview->started_at ?? now(),
            ]);

            return $mock;
        });
    }

    private function blueprintFor(EmployerInterview $interview): MockBlueprint
    {
        $round = $interview->roundDefinition()->with('job.workspace:id,name')->first();
        $job = $round?->job;
        $questions = array_values(array_filter((array) $interview->question_set, 'is_array'));
        $first = (string) ($questions[0]['text'] ?? '');
        $role = mb_substr((string) ($job?->title ?? 'this role'), 0, 255);
        $company = $job?->workspace?->name ?? 'the hiring team';

        $competencies = array_values(array_filter(array_map('strval', (array) ($round?->focus_skills ?? []))))
            ?: array_slice(array_values(array_unique(array_filter(array_map(fn (array $q) => (string) ($q['skill'] ?? ''), $questions)))), 0, 6)
            ?: array_slice($job?->skills ?? [], 0, 6)
            ?: ['role fit', 'communication'];

        return MockBlueprint::query()->firstOrCreate(
            ['tenant_id' => $interview->tenant_id, 'employer_job_round_id' => $interview->employer_job_round_id],
            [
                'role_title' => $role,
                'skill' => null,
                'competencies' => array_slice($competencies, 0, 6),
                'opening_question' => mb_substr($first !== ''
                    ? $first
                    : "This is your {$interview->round_name} for {$role} at {$company}. Introduce yourself and tell me why you fit this role.", 0, 500),
                'max_questions' => max(3, min(10, count($questions) ?: 6)),
                'is_active' => false,
            ],
        );
    }
}

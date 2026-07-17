<?php

declare(strict_types=1);

namespace App\Actions\Mentoring;

use App\Actions\Telemetry\RecordActivity;
use App\Enums\ActivityType;
use App\Models\MentorSession;
use Illuminate\Validation\ValidationException;

/**
 * Post-session mentor feedback (PRD §6.11): a 0–100 readiness score plus
 * strengths/improvements. Marks the session completed, logs telemetry for
 * the student, and the score flows into PRI via ScoreCalculator's mentor
 * blend — human judgment calibrating the machine number.
 */
final readonly class SubmitMentorFeedback
{
    public function __construct(private RecordActivity $activity) {}

    /**
     * @param  array{score: int, strengths: string, improvements: string}  $input
     */
    public function handle(MentorSession $session, array $input): MentorSession
    {
        if ($session->starts_at->isFuture()) {
            throw ValidationException::withMessages(['session' => 'Feedback opens once the session has started.']);
        }

        if (! in_array($session->status, [MentorSession::STATUS_BOOKED, MentorSession::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages(['session' => 'This session cannot take feedback.']);
        }

        $score = max(0, min(100, $input['score']));

        $session->update([
            'status' => MentorSession::STATUS_COMPLETED,
            'feedback_score' => $score,
            'feedback' => [
                'score' => $score,
                'strengths' => $input['strengths'],
                'improvements' => $input['improvements'],
            ],
        ]);

        if ($session->student !== null) {
            $this->activity->handle($session->student, ActivityType::MentorFeedback, $session, $score);
        }

        return $session->refresh();
    }
}

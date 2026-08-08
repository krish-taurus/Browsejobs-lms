<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Enums\EmployerApplicationStage;
use App\Enums\VerificationKind;
use App\Enums\VerificationStatus;
use App\Models\CandidateVerificationCheck;
use App\Models\CvProfile;
use App\Models\EmployerJobApplication;
use App\Models\MockInterview;

/**
 * The full candidate view an employer opens from the pipeline (PRD-E F17).
 *
 * Assembles what already exists rather than storing anything new: the
 * application, the graded interview and its scorecard, the verification
 * checks, and the parsed CV.
 *
 * Two disclosure rules hold throughout. Contact details stay hidden until
 * Shortlisted, matching the application list. And verification returns the
 * *result* of a check only — never the document behind it, never the
 * reference number the candidate submitted. An employer is entitled to know
 * that employment was confirmed; they are not entitled to the PF record.
 */
final readonly class CandidateProfile
{
    public function __construct(private EmployerJobApplication $application) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $application = $this->application->loadMissing(['candidate', 'job', 'mockInterview']);
        $candidate = $application->candidate;

        $stage = $application->stage instanceof EmployerApplicationStage
            ? $application->stage
            : EmployerApplicationStage::tryFrom((string) $application->stage);

        $contactVisible = $stage !== null && ! in_array($stage, [
            EmployerApplicationStage::Applied,
            EmployerApplicationStage::Graded,
        ], true);

        return [
            'application' => [
                'id' => $application->id,
                'stage' => $stage?->value,
                'mock_score' => $application->mock_score,
                'mock_attempts' => $application->mock_attempts,
                'applied_at' => $application->created_at?->toIso8601String(),
                'graded_at' => $application->graded_at?->toIso8601String(),
            ],
            'candidate' => [
                'id' => $candidate?->id,
                'name' => $candidate?->name,
                // Held back until Shortlisted, same rule as the list view.
                'email' => $contactVisible ? $candidate?->email : null,
                'phone' => $contactVisible ? $candidate?->phone : null,
                'contact_visible' => $contactVisible,
            ],
            'cv' => $this->cv($candidate?->id),
            'verification' => $this->verification($candidate?->id),
            'interview' => $this->interview($application->mockInterview),
        ];
    }

    /**
     * @return array{summary: string|null, skills: list<string>, experience: list<array<string, mixed>>, education: list<array<string, mixed>>}|null
     */
    private function cv(?int $userId): ?array
    {
        if ($userId === null) {
            return null;
        }

        $profile = CvProfile::query()->where('user_id', $userId)->first();

        if ($profile === null) {
            return null;
        }

        $data = is_array($profile->data) ? $profile->data : [];

        return [
            'summary' => is_string($data['summary'] ?? null) ? $data['summary'] : null,
            'skills' => array_values(array_filter((array) ($data['skills'] ?? []), 'is_string')),
            'experience' => array_values(array_filter((array) ($data['experience'] ?? []), 'is_array')),
            'education' => array_values(array_filter((array) ($data['education'] ?? []), 'is_array')),
        ];
    }

    /**
     * Verification results only. No provider reference, no evidence payload,
     * no document path — an employer learns the outcome, not the record.
     *
     * @return array{badge: bool, checks: list<array{kind: string, label: string, status: string, status_label: string, verified_at: string|null}>}
     */
    private function verification(?int $userId): array
    {
        $rows = $userId === null
            ? collect()
            : CandidateVerificationCheck::query()->where('user_id', $userId)->get()->keyBy(
                fn (CandidateVerificationCheck $c): string => $c->kind->value,
            );

        /** @var list<string> $required */
        $required = config('verification.badge_requires', []);
        $checks = [];
        $held = 0;

        foreach (VerificationKind::cases() as $kind) {
            $row = $rows->get($kind->value);
            $status = $row?->effectiveStatus() ?? VerificationStatus::NotStarted;

            if ($status->counts() && in_array($kind->value, $required, true)) {
                $held++;
            }

            $checks[] = [
                'kind' => $kind->value,
                'label' => $kind->label(),
                'status' => $status->value,
                'status_label' => $status->label(),
                'verified_at' => $status->counts() ? $row?->verified_at?->toIso8601String() : null,
            ];
        }

        return [
            'badge' => $required !== [] && $held === count($required),
            'checks' => $checks,
        ];
    }

    /**
     * The graded interview: overall, per-competency scores, and the moments
     * the grader called out.
     *
     * `session` reports only what is actually recorded. Camera and
     * window-switch signals are not captured yet, and saying so is better
     * than implying a clean session we never observed.
     *
     * @return array<string, mixed>|null
     */
    private function interview(?MockInterview $interview): ?array
    {
        if ($interview === null) {
            return null;
        }

        $card = is_array($interview->scorecard) ? $interview->scorecard : [];

        $competencies = [];
        foreach ((array) ($card['competencies'] ?? []) as $entry) {
            if (is_array($entry) && is_string($entry['name'] ?? null)) {
                $competencies[] = [
                    'name' => $entry['name'],
                    'score' => isset($entry['score']) ? (int) $entry['score'] : null,
                ];
            }
        }

        return [
            'overall' => $interview->overall_score !== null ? (int) $interview->overall_score : null,
            'graded_by' => $interview->scorecard_source, // ai | fallback
            'competencies' => $competencies,
            'strengths' => array_values(array_filter((array) ($card['strong_moments'] ?? []), 'is_string')),
            'concerns' => array_values(array_filter((array) ($card['weak_moments'] ?? []), 'is_string')),
            'session' => [
                'mode' => $interview->mode,
                'duration_seconds' => $interview->duration_seconds,
                'status' => $interview->status,
                'completed_at' => $interview->completed_at?->toIso8601String(),
                // Interviews are video-proctored operationally, but no
                // integrity signal reaches this payload yet: employer_interviews
                // has no column for the recording, out-of-frame time, focus
                // loss, pasted answers or per-answer timing, and
                // quiz_attempts.integrity covers LMS quizzes only. Until those
                // are recorded against an employer round and returned here,
                // this stays false — reporting a clean session we never
                // measured is the one thing the trust firewall exists to
                // prevent. /for-employers describes the proctoring an employer
                // is promised; this screen must not claim it until the data
                // backs it up.
                'proctoring_captured' => false,
            ],
        ];
    }
}

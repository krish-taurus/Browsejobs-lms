<?php

declare(strict_types=1);

namespace App\Actions\JobFeed;

use App\Enums\AiPurpose;
use App\Models\JobFeedItem;
use App\Models\RealInterviewQuestion;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\JsonOutput;
use Throwable;

/**
 * "Potential questions for this job" (ADR 0048). Generated once per posting and
 * cached on the item — the cost is per job, not per viewer. Two layers, both
 * honest about their source:
 * - real: approved questions from the real-interview bank whose role matches
 *   this posting (never invented — they were actually asked);
 * - jd: AI questions derived strictly from this posting's JD, with a deterministic
 *   skills-based fallback when AI is unavailable, so the surface never 500s.
 */
final readonly class GenerateJobPrepQuestions
{
    private const REAL_LIMIT = 5;

    public function __construct(private AiGateway $gateway) {}

    /**
     * @return list<array{question: string, why: string|null, source: string}>
     */
    public function handle(User $actor, JobFeedItem $item): array
    {
        if (! empty($item->prep_questions)) {
            return $item->prep_questions;
        }

        $questions = [...$this->fromRealBank($item), ...$this->fromJd($actor, $item)];

        $item->update(['prep_questions' => $questions]);

        return $questions;
    }

    /**
     * Approved real-interview questions for this role — actually asked, so they
     * lead the list.
     *
     * @return list<array{question: string, why: string|null, source: string}>
     */
    private function fromRealBank(JobFeedItem $item): array
    {
        $role = mb_strtolower(trim((string) ($item->role_title ?? $item->title)));
        if ($role === '') {
            return [];
        }

        // Loose role match: any word of length ≥4 from the posting's role title.
        $words = array_values(array_filter(preg_split('/[^a-z0-9]+/', $role) ?: [], fn ($w) => mb_strlen($w) >= 4));
        if ($words === []) {
            return [];
        }

        return RealInterviewQuestion::query()
            ->where('status', RealInterviewQuestion::STATUS_APPROVED)
            ->where(function ($q) use ($words): void {
                foreach ($words as $word) {
                    $q->orWhere('role_title', 'like', "%{$word}%");
                }
            })
            ->orderByDesc('asked_count')
            ->limit(self::REAL_LIMIT)
            ->pluck('question')
            ->map(fn ($question) => [
                'question' => (string) $question,
                'why' => 'Asked in a real interview for this role.',
                'source' => 'real',
            ])
            ->all();
    }

    /**
     * @return list<array{question: string, why: string|null, source: string}>
     */
    private function fromJd(User $actor, JobFeedItem $item): array
    {
        try {
            $result = $this->gateway->complete($actor, AiPurpose::InterviewPrep, 'job_questions', 1, [
                'role' => (string) ($item->role_title ?? $item->title),
                'company' => $item->company,
                'jd' => mb_substr($item->description, 0, 8000),
            ], ['max_tokens' => 1200]);

            $data = JsonOutput::object($result->text);
            $rows = is_array($data) && is_array($data['questions'] ?? null) ? $data['questions'] : null;

            if ($rows !== null) {
                $questions = [];
                foreach ($rows as $row) {
                    $question = trim((string) ($row['question'] ?? ''));
                    if ($question !== '') {
                        $questions[] = [
                            'question' => mb_substr($question, 0, 500),
                            'why' => isset($row['why']) && trim((string) $row['why']) !== '' ? mb_substr(trim((string) $row['why']), 0, 300) : null,
                            'source' => 'jd',
                        ];
                    }
                }

                if ($questions !== []) {
                    return array_slice($questions, 0, 10);
                }
            }
        } catch (Throwable) {
            // Budget exceeded / provider down — fall through to the skills fallback.
        }

        return $this->fallback($item);
    }

    /**
     * Deterministic fallback from the extracted skills — the surface still works
     * with AI off, and every question is still grounded in the JD.
     *
     * @return list<array{question: string, why: string|null, source: string}>
     */
    private function fallback(JobFeedItem $item): array
    {
        $role = (string) ($item->role_title ?? $item->title);

        return collect(array_slice($item->extracted_skills ?? [], 0, 6))
            ->map(fn (string $skill) => [
                'question' => 'Walk me through how you have used '.$skill.' in a real project — what did you build and what went wrong?',
                'why' => "The JD lists {$skill}.",
                'source' => 'jd',
            ])
            ->concat([[
                'question' => "Why do you want this {$role} role at {$item->company}?",
                'why' => 'Role-fit is asked in almost every interview.',
                'source' => 'jd',
            ]])
            ->values()
            ->all();
    }
}

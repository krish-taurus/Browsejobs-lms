<?php

declare(strict_types=1);

namespace App\Actions\Boosters;

use App\Enums\AiPurpose;
use App\Models\CareerBooster;
use App\Models\MockBlueprint;
use App\Models\RealInterviewQuestion;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\JsonOutput;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Career+ interview-day prep pack (PRD §6.20). Pulls the REAL questions from the approved
 * interview bank (ADR 0030) for the student's role/course — these are actual questions
 * asked in real interviews, never invented — and the AI's job is only to group and
 * prioritise them into focus areas with short prep tips. If there is no bank coverage the
 * pack is honestly empty; if the AI fails it degrades to the raw questions grouped by topic.
 */
final readonly class GeneratePrepPack
{
    public function __construct(private AiGateway $gateway) {}

    public function handle(User $student): CareerBooster
    {
        $blueprint = MockBlueprint::activeFor($student);
        $questions = $this->bankQuestions($blueprint);
        [$content, $source] = $this->compose($student, $blueprint?->role_title, $questions);

        return CareerBooster::query()->updateOrCreate(
            ['user_id' => $student->id, 'kind' => CareerBooster::KIND_PREP_PACK],
            ['tenant_id' => $student->tenant_id, 'content' => $content, 'content_source' => $source],
        );
    }

    /**
     * Approved bank questions for the student's role or course.
     *
     * @return Collection<int, RealInterviewQuestion>
     */
    private function bankQuestions(?MockBlueprint $blueprint): Collection
    {
        if ($blueprint === null) {
            return collect();
        }

        return RealInterviewQuestion::query()
            ->where('status', RealInterviewQuestion::STATUS_APPROVED)
            ->where(fn ($q) => $q->where('course_id', $blueprint->course_id)->orWhere('role_title', $blueprint->role_title))
            ->orderByDesc('asked_count')
            ->limit(30)
            ->get(['question', 'topic_tags', 'difficulty', 'asked_count']);
    }

    /**
     * @param  Collection<int, RealInterviewQuestion>  $questions
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function compose(User $student, ?string $role, Collection $questions): array
    {
        if ($questions->isEmpty()) {
            return [['role' => $role, 'focus_areas' => [], 'note' => 'No interview-bank coverage for your role yet.'], 'fallback'];
        }

        try {
            $result = $this->gateway->complete($student, AiPurpose::InterviewPrep, 'interview_prep', 1, [
                'role' => $role ?? 'the role',
                'questions' => $questions->map(fn (RealInterviewQuestion $q) => '- ['.implode(',', (array) $q->topic_tags).'] '.$q->question)->implode("\n"),
            ], ['max_tokens' => 1400]);

            $decoded = JsonOutput::object($result->text);
            if (is_array($decoded) && ! empty($decoded['focus_areas'])) {
                return [$decoded, 'ai'];
            }
        } catch (Throwable) {
            // fall through
        }

        return [$this->fallback($role, $questions), 'fallback'];
    }

    /**
     * Raw real questions grouped by their first topic tag — no invention, just structure.
     *
     * @param  Collection<int, RealInterviewQuestion>  $questions
     * @return array<string, mixed>
     */
    private function fallback(?string $role, Collection $questions): array
    {
        $areas = $questions
            ->groupBy(fn (RealInterviewQuestion $q) => (string) (($q->topic_tags[0] ?? null) ?: 'General'))
            ->map(fn (Collection $qs, string $topic) => [
                'topic' => $topic,
                'questions' => $qs->pluck('question')->take(6)->values()->all(),
                'tips' => ['These are drawn from real interviews for this role — rehearse a crisp, structured answer for each.'],
            ])
            ->values()
            ->all();

        return ['role' => $role, 'focus_areas' => $areas];
    }
}

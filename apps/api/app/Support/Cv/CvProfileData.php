<?php

declare(strict_types=1);

namespace App\Support\Cv;

use App\Enums\BatchMemberStatus;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Certificate;
use App\Models\CodeSubmission;
use App\Models\CodingLab;
use App\Models\MockInterview;
use App\Models\Module;
use App\Models\TopicCompletion;
use App\Models\User;

/**
 * Assembles the FACTS a CV may contain (PRD §6.7): completed modules,
 * passed labs as projects, certificates, mock-interview strengths. This is
 * the compliance boundary — the LLM only rephrases what this class returns
 * and is forbidden from inventing employers or experience (never-claims,
 * PRD §14.3). Deterministic, so the fallback CV builds from the same truth.
 */
final class CvProfileData
{
    /**
     * @return array{
     *   name: string, email: string|null, phone: string|null,
     *   role_title: string|null, courses: list<string>,
     *   completed_modules: list<string>, projects: list<array{name: string, detail: string}>,
     *   certifications: list<string>, mock_strengths: list<string>, best_mock_score: int|null
     * }
     */
    public function for(User $student): array
    {
        $courses = BatchMember::query()
            ->where('user_id', $student->id)
            ->whereIn('status', array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying()))
            ->pluck('batch_id')
            ->pipe(fn ($ids) => Batch::query()->whereIn('id', $ids)->with('course:id,name')->get())
            ->pluck('course')
            ->filter();

        $completedTopicIds = TopicCompletion::query()->where('user_id', $student->id)->pluck('topic_id');

        $modules = Module::query()
            ->whereHas('topics', fn ($q) => $q->whereIn('id', $completedTopicIds))
            ->orderBy('position')
            ->get(['id', 'name'])
            // Only modules where EVERY topic is done count as completed skill.
            ->filter(function (Module $module) use ($completedTopicIds): bool {
                $ids = $module->topics()->pluck('id');

                return $ids->isNotEmpty() && $ids->diff($completedTopicIds)->isEmpty();
            })
            ->pluck('name')
            ->values();

        $projects = CodeSubmission::query()
            ->where('user_id', $student->id)
            ->where('kind', 'submit')
            ->whereColumn('passed_tests', 'total_tests')
            ->where('total_tests', '>', 0)
            ->with('lesson:id,title')
            ->latest('id')
            ->get()
            ->unique('lesson_id')
            ->take(8)
            ->map(fn (CodeSubmission $s) => [
                'name' => (string) ($s->lesson?->title ?? 'Coding lab'),
                'detail' => "Passed all {$s->total_tests} tests in {$s->language}"
                    .$this->labContext($s->lesson_id),
            ])
            ->values()
            ->all();

        $best = MockInterview::query()
            ->where('user_id', $student->id)
            ->where('status', MockInterview::STATUS_COMPLETED)
            ->orderByDesc('overall_score')
            ->first();

        return [
            'name' => $student->name,
            'email' => $student->email,
            'phone' => $student->phone,
            'role_title' => $best?->blueprint?->role_title
                ?? MockInterview::query()->where('user_id', $student->id)->latest('id')->first()?->blueprint?->role_title,
            'courses' => $courses->pluck('name')->unique()->values()->all(),
            'completed_modules' => $modules->all(),
            'projects' => $projects,
            'certifications' => Certificate::query()
                ->where('user_id', $student->id)
                ->where('status', '!=', 'revoked')
                ->pluck('title')
                ->all(),
            'mock_strengths' => array_slice((array) ($best?->scorecard['strong_moments'] ?? []), 0, 5),
            'best_mock_score' => $best?->overall_score,
        ];
    }

    private function labContext(?int $lessonId): string
    {
        if ($lessonId === null) {
            return '';
        }

        $instructions = CodingLab::query()->where('lesson_id', $lessonId)->value('instructions');
        if (! is_string($instructions) || trim($instructions) === '') {
            return '';
        }

        return ' — '.str(trim($instructions))->limit(120)->toString();
    }
}

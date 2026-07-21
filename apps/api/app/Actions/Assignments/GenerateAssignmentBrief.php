<?php

declare(strict_types=1);

namespace App\Actions\Assignments;

use App\Enums\AiPurpose;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Tenancy\TenantContext;

/**
 * Draft an assignment (title, instructions, rubric) from a lesson's module via
 * the AI gateway — the "auto-generate" option beside manual authoring. Billed
 * to the triggering staff user (never a student). Returns a DRAFT only; nothing
 * is persisted until the trainer reviews and saves it, so unreviewed AI content
 * never reaches students.
 */
final readonly class GenerateAssignmentBrief
{
    public function __construct(private AiGateway $gateway) {}

    /**
     * @return array{title: string, instructions: string, rubric: list<array{criterion: string, max_points: int}>, max_points: int}|null
     */
    public function handle(Lesson $lesson, User $actor): ?array
    {
        return app(TenantContext::class)->run($lesson->tenant, function () use ($lesson, $actor): ?array {
            $module = $lesson->topic?->module;

            $result = $this->gateway->complete(
                $actor,
                AiPurpose::AssignmentGen,
                'assignment_gen',
                1,
                $this->vars($module),
                ['max_tokens' => 1200],
            );

            return $this->parse($result->text);
        });
    }

    /**
     * @return array<string, string>
     */
    private function vars(?Module $module): array
    {
        $course = $module?->course;

        return [
            'course' => $course?->name ?? 'the course',
            'module' => $module?->name ?? 'the module',
            'topics' => $module?->topics()->pluck('name')->implode(', ') ?: 'general concepts',
            'course_description' => (string) ($course?->description ?? ''),
        ];
    }

    /**
     * Extract + validate the assignment object from the model's response.
     * Tolerates markdown fences and surrounding prose. Returns null if unusable.
     *
     * @return array{title: string, instructions: string, rubric: list<array{criterion: string, max_points: int}>, max_points: int}|null
     */
    private function parse(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($decoded) || ! isset($decoded['title'], $decoded['instructions'])) {
            return null;
        }

        $rubric = [];
        foreach ((array) ($decoded['rubric'] ?? []) as $row) {
            if (! is_array($row) || ! isset($row['criterion'], $row['max_points'])) {
                continue;
            }
            $points = (int) $row['max_points'];
            if ($points <= 0) {
                continue;
            }
            $rubric[] = ['criterion' => trim((string) $row['criterion']), 'max_points' => $points];
        }

        if ($rubric === []) {
            $rubric[] = ['criterion' => 'Completeness and correctness', 'max_points' => 100];
        }

        return [
            'title' => trim((string) $decoded['title']),
            'instructions' => trim((string) $decoded['instructions']),
            'rubric' => $rubric,
            'max_points' => array_sum(array_column($rubric, 'max_points')),
        ];
    }
}

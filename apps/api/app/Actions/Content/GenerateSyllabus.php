<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\AiPurpose;
use App\Enums\SyllabusStatus;
use App\Models\Syllabus;
use App\Models\User;
use App\Services\AI\AiBudgetExceeded;
use App\Services\AI\AiGateway;
use App\Support\Syllabus\SyllabusHasher;
use App\Support\Tenancy\TenantContext;
use Throwable;

/**
 * Drafts a course syllabus from curriculum data via the AI gateway (PRD §6.2).
 * Prose output (no fragile JSON parse). Billed to the triggering staff user, never
 * a student. Fail-safe like ReportNarrator: on a budget/transport failure or empty
 * output it leaves `content` untouched (manual authoring) and never throws. The row
 * stays a `draft` — a trainer approves it. The previously rendered artifact (if any)
 * is left intact so the last approved syllabus keeps serving.
 */
final readonly class GenerateSyllabus
{
    public function __construct(
        private AiGateway $gateway,
        private SyllabusHasher $hasher,
    ) {}

    /** @return bool whether AI notes were written */
    public function handle(Syllabus $syllabus, User $actor): bool
    {
        return app(TenantContext::class)->run($syllabus->tenant, function () use ($syllabus, $actor): bool {
            $course = $syllabus->course;
            if ($course === null) {
                return false;
            }

            $hash = $this->hasher->hash($course);
            $vars = [
                'tenant' => $course->tenant?->name ?? 'BrowseJobs',
                'course' => (string) $course->name,
                'tagline' => (string) $course->tagline,
                'course_description' => (string) $course->description,
                'curriculum' => $this->hasher->outline($course) ?: 'No modules defined yet.',
            ];

            try {
                $result = $this->gateway->complete(
                    $actor,
                    AiPurpose::Syllabus,
                    'syllabus',
                    (int) config('syllabus.ai.prompt_version', 1),
                    $vars,
                    ['max_tokens' => (int) config('syllabus.ai.max_tokens', 2000)],
                );
            } catch (AiBudgetExceeded|Throwable) {
                return false; // Leave content for manual authoring; do not throw.
            }

            $prose = $this->sanitize($result->text);
            if ($prose === '') {
                return false;
            }

            // Regeneration produces a fresh, unapproved draft. storage_path /
            // render_status / is_stale are left untouched: the last approved artifact
            // keeps serving, and the listener owns the staleness flag.
            $syllabus->update([
                'content' => $prose,
                'curriculum_hash' => $hash,
                'generated_by' => $actor->id,
                'status' => SyllabusStatus::Draft->value,
            ]);

            return true;
        });
    }

    private function sanitize(string $text): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', trim($text)) ?? '';

        return mb_substr($clean, 0, (int) config('syllabus.ai.max_chars', 12000));
    }
}

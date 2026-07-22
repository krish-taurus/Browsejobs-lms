<?php

declare(strict_types=1);

namespace App\Actions\Flashcards;

use App\Enums\AiPurpose;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\Module;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Tenancy\TenantContext;

/**
 * Draft study flashcards for a lesson from its material (the lesson's approved
 * notes, falling back to the module's topic list) via the AI gateway — the
 * "auto-generate" option beside manual authoring. Billed to the triggering staff
 * user. Returns a DRAFT only; nothing is persisted until the trainer reviews and
 * saves it, so unreviewed AI content never reaches students.
 */
final readonly class GenerateFlashcards
{
    public function __construct(private AiGateway $gateway) {}

    /**
     * @return list<array{front: string, back: string}>|null
     */
    public function handle(Lesson $lesson, User $actor, int $count = 10): ?array
    {
        return app(TenantContext::class)->run($lesson->tenant, function () use ($lesson, $actor, $count): ?array {
            $module = $lesson->topic?->module;

            $result = $this->gateway->complete(
                $actor,
                AiPurpose::FlashcardGen,
                'flashcard_gen',
                1,
                [
                    'count' => (string) $count,
                    'course' => $module?->course?->name ?? 'the course',
                    'module' => $module?->name ?? 'the module',
                    'lesson' => $lesson->title ?? 'this class',
                    'material' => $this->material($lesson, $module),
                ],
                ['max_tokens' => 1400],
            );

            return $this->parse($result->text);
        });
    }

    private function material(Lesson $lesson, ?Module $module): string
    {
        $note = LessonNote::query()->where('lesson_id', $lesson->id)->first();
        if ($note !== null && $note->notes !== null && trim((string) $note->notes) !== '') {
            return (string) $note->notes;
        }

        $topics = $module?->topics()->pluck('name')->implode(', ');

        return $topics !== null && $topics !== '' ? "Topics: {$topics}" : 'General concepts for this class.';
    }

    /**
     * Extract + validate the flashcard array. Tolerates fences/prose.
     *
     * @return list<array{front: string, back: string}>|null
     */
    private function parse(string $text): ?array
    {
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($decoded)) {
            return null;
        }

        $cards = [];
        foreach ($decoded as $row) {
            if (! is_array($row) || ! isset($row['front'], $row['back'])) {
                continue;
            }
            $front = trim((string) $row['front']);
            $back = trim((string) $row['back']);
            if ($front === '' || $back === '') {
                continue;
            }
            $cards[] = ['front' => $front, 'back' => $back];
        }

        return $cards === [] ? null : $cards;
    }
}

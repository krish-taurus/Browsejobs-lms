<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\AiPurpose;
use App\Enums\NoteStatus;
use App\Models\LessonNote;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Tenancy\TenantContext;
use Throwable;

/**
 * Generates AI study notes from a lesson's transcript (PRD §6.10). Prose output (no
 * JSON parse), billed to the generating staff user. Fail-safe: on a budget/transport
 * failure it leaves `notes` untouched (the trainer authors them by hand) and never
 * throws. The note stays draft — a trainer must approve before students see it.
 */
final readonly class GenerateNotes
{
    private const MAX_CHARS = 8000;

    public function __construct(private AiGateway $gateway) {}

    public function handle(LessonNote $note, User $actor): bool
    {
        return app(TenantContext::class)->run($note->tenant, function () use ($note, $actor): bool {
            $module = $note->lesson?->topic?->module;

            try {
                $result = $this->gateway->complete(
                    $actor,
                    AiPurpose::Content,
                    'notes',
                    (int) config('content.notes.prompt_version', 1),
                    [
                        'course' => $module?->course?->name ?? '',
                        'module' => $module?->name ?? '',
                        'lesson' => $note->lesson?->title ?? '',
                        'transcript' => $note->transcript,
                    ],
                    ['max_tokens' => (int) config('content.notes.max_tokens', 1200)],
                );
            } catch (Throwable) {
                return false; // leave notes null for manual authoring — never throw
            }

            $prose = $this->sanitize($result->text);
            if ($prose === '') {
                return false;
            }

            $note->update([
                'notes' => $prose,
                'generated_by' => $actor->id,
                'status' => NoteStatus::Draft->value, // still needs approval
            ]);

            return true;
        });
    }

    private function sanitize(string $text): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', trim($text)) ?? '';

        return mb_substr($clean, 0, (int) config('content.notes.max_chars', self::MAX_CHARS));
    }
}

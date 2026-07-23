<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\AiPurpose;
use App\Enums\LessonType;
use App\Enums\NoteSource;
use App\Enums\NoteStatus;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Tenancy\TenantContext;

/**
 * Generate a day's beginner study notes from its topic keywords (ADR 0049) —
 * absolute-fresher language with tiny examples, per the simple_notes prompt.
 * The notes land on the topic's `notes` lesson (auto-created) as a DRAFT via
 * the existing LessonNote pipeline, so the trainer's approve step and the PDF
 * render/upload flow apply unchanged and students never see unapproved output.
 */
final readonly class GenerateSimpleNotes
{
    public function __construct(private AiGateway $gateway) {}

    public function handle(Topic $topic, User $actor): ?LessonNote
    {
        return app(TenantContext::class)->run($topic->tenant, function () use ($topic, $actor): ?LessonNote {
            $module = $topic->module;
            $keywords = implode(', ', (array) ($topic->keywords ?? []));

            $result = $this->gateway->complete(
                $actor,
                AiPurpose::Content,
                'simple_notes',
                1,
                [
                    'course' => $module?->course?->name ?? 'the course',
                    'module' => $module?->name ?? 'the module',
                    'day_number' => (string) ($topic->day_number ?? $topic->position + 1),
                    'topic' => $topic->name,
                    'summary' => (string) ($topic->summary ?? ''),
                    'keywords' => $keywords !== '' ? $keywords : $topic->name,
                ],
                ['max_tokens' => 3500],
            );

            if (trim($result->text) === '') {
                return null;
            }

            $lesson = $this->notesLesson($topic);

            // The topic outline stands in for a transcript: these notes come from
            // the day plan, not a recorded class.
            $outline = trim("Day plan: {$topic->name}\n".(string) $topic->summary."\nKeywords: {$keywords}");

            return LessonNote::query()->updateOrCreate(
                ['lesson_id' => $lesson->id],
                [
                    'tenant_id' => $topic->tenant_id,
                    'transcript' => $outline,
                    'notes' => trim($result->text),
                    'source' => NoteSource::Paste->value,
                    'status' => NoteStatus::Draft->value,
                    'generated_by' => $actor->id,
                    'approved_by' => null,
                    'approved_at' => null,
                ],
            );
        });
    }

    /** The topic's notes lesson, created on first use. */
    private function notesLesson(Topic $topic): Lesson
    {
        $existing = $topic->lessons()->where('type', LessonType::Notes->value)->first();
        if ($existing !== null) {
            return $existing;
        }

        return Lesson::query()->create([
            'tenant_id' => $topic->tenant_id,
            'topic_id' => $topic->id,
            'title' => $topic->name.' — study notes',
            'type' => LessonType::Notes->value,
            'position' => $topic->lessons()->count(),
        ]);
    }
}

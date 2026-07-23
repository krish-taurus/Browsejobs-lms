<?php

declare(strict_types=1);

namespace App\Actions\Quizzes;

use App\Enums\AiPurpose;
use App\Enums\QuizSource;
use App\Enums\QuizStatus;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Generates draft MCQs for a quiz from its module's content via the AI gateway
 * (PRD §6.5). Billed to the triggering staff user (never a student). Robustly
 * parses the model's JSON, validates each item, and replaces the quiz's draft
 * questions inside a transaction. The quiz stays `draft` — a trainer approves it.
 */
final readonly class GenerateQuiz
{
    public function __construct(private AiGateway $gateway) {}

    /** @return int number of questions written */
    public function handle(Quiz $quiz, User $actor, int $count = 8): int
    {
        return app(TenantContext::class)->run($quiz->tenant, function () use ($quiz, $actor, $count): int {
            $topic = $quiz->lesson?->topic;
            $vars = $this->vars($topic?->module, $count, $topic);

            $result = $this->gateway->complete(
                $actor,
                AiPurpose::QuizGen,
                'quiz_gen',
                1,
                $vars,
                ['max_tokens' => (int) config('ai.tutor.max_answer_tokens', 700) * 3],
            );

            $items = $this->parse($result->text);
            if ($items === []) {
                return 0;
            }

            return DB::transaction(function () use ($quiz, $items): int {
                $quiz->questions()->delete();

                $position = 0;
                foreach ($items as $item) {
                    QuizQuestion::query()->create([
                        'tenant_id' => $quiz->tenant_id,
                        'quiz_id' => $quiz->id,
                        'prompt' => $item['prompt'],
                        'options' => $item['options'],
                        'correct_index' => $item['correct_index'],
                        'explanation' => $item['explanation'],
                        'position' => $position++,
                    ]);
                }

                // A freshly (re)generated quiz is always an unapproved AI draft.
                $quiz->update(['source' => QuizSource::Ai->value, 'status' => QuizStatus::Draft->value]);

                return $position;
            });
        });
    }

    /**
     * @return array<string, string>
     */
    private function vars(?Module $module, int $count, ?Topic $topic = null): array
    {
        $course = $module?->course;
        $topics = $module?->topics()->pluck('name')->implode(', ') ?? '';

        // A day-builder topic (ADR 0049) carries its own keywords/summary — focus
        // the questions on that single day instead of the whole module.
        if ($topic !== null && ($topic->keywords !== null || filled($topic->summary))) {
            $topics = trim(sprintf(
                '%s%s. %s Keywords: %s',
                $topic->day_number ? "Day {$topic->day_number}: " : '',
                $topic->name,
                (string) ($topic->summary ?? ''),
                implode(', ', (array) ($topic->keywords ?? [])) ?: $topic->name,
            ));
        }

        return [
            'count' => (string) $count,
            'course' => $course?->name ?? 'the course',
            'module' => $module?->name ?? 'the module',
            'topics' => $topics !== '' ? $topics : 'general concepts',
            'course_description' => (string) ($course?->description ?? ''),
        ];
    }

    /**
     * Extract + validate MCQ items from the model's response. Tolerates markdown
     * fences and surrounding prose; drops malformed items.
     *
     * @return list<array{prompt: string, options: list<string>, correct_index: int, explanation: string}>
     */
    private function parse(string $text): array
    {
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $row) {
            if (! is_array($row) || ! isset($row['prompt'], $row['options'], $row['correct_index'])) {
                continue;
            }

            $options = array_values(array_filter((array) $row['options'], 'is_string'));
            $correct = (int) $row['correct_index'];

            if (count($options) < 2 || $correct < 0 || $correct >= count($options)) {
                continue;
            }

            $items[] = [
                'prompt' => trim((string) $row['prompt']),
                'options' => $options,
                'correct_index' => $correct,
                'explanation' => trim((string) ($row['explanation'] ?? '')),
            ];
        }

        return $items;
    }
}

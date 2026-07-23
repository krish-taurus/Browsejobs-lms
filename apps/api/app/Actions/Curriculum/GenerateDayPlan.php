<?php

declare(strict_types=1);

namespace App\Actions\Curriculum;

use App\Enums\AiPurpose;
use App\Models\Module;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Tenancy\TenantContext;

/**
 * Draft one teaching day for a module from trainer-picked keywords (ADR 0049) —
 * the "generate with AI" option beside manual day entry. Billed to the staff
 * actor. Returns a DRAFT only; nothing is persisted until the trainer reviews
 * and saves it, so unreviewed AI content never reaches the curriculum.
 */
final readonly class GenerateDayPlan
{
    public function __construct(private AiGateway $gateway) {}

    /**
     * @param  list<string>  $keywords
     * @return array{name: string, summary: string, subtopics: list<string>, keywords: list<string>}|null
     */
    public function handle(Module $module, User $actor, array $keywords, int $dayNumber): ?array
    {
        return app(TenantContext::class)->run($module->tenant, function () use ($module, $actor, $keywords, $dayNumber): ?array {
            $existing = $module->topics()
                ->orderByRaw('COALESCE(day_number, position + 1)')
                ->get()
                ->map(fn ($t) => ($t->day_number ? "Day {$t->day_number}: " : '').$t->name)
                ->implode(' · ');

            $result = $this->gateway->complete(
                $actor,
                AiPurpose::DayPlan,
                'day_plan',
                1,
                [
                    'course' => $module->course?->name ?? 'the course',
                    'module' => $module->name,
                    'day_number' => (string) $dayNumber,
                    'keywords' => implode(', ', $keywords),
                    'existing_days' => $existing !== '' ? $existing : 'none yet',
                ],
                ['max_tokens' => 900],
            );

            return $this->parse($result->text);
        });
    }

    /**
     * @return array{name: string, summary: string, subtopics: list<string>, keywords: list<string>}|null
     */
    private function parse(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($decoded) || trim((string) ($decoded['name'] ?? '')) === '') {
            return null;
        }

        $strings = fn ($value): array => array_values(array_filter(
            array_map(fn ($v) => trim((string) $v), is_array($value) ? $value : []),
            fn (string $v) => $v !== '',
        ));

        return [
            'name' => mb_substr(trim((string) $decoded['name']), 0, 190),
            'summary' => trim((string) ($decoded['summary'] ?? '')),
            'subtopics' => $strings($decoded['subtopics'] ?? []),
            'keywords' => array_slice($strings($decoded['keywords'] ?? []), 0, 10),
        ];
    }
}

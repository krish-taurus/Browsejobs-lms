<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Enums\AiPurpose;
use App\Models\Lesson;
use App\Models\ProjectSpec;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Tenancy\TenantContext;

/**
 * Draft a structured system architecture (overview + layers + flows) for a
 * project lesson from its saved title, details and tech stack — the
 * "AI generates the architecture" option beside uploading one. Billed to the
 * triggering staff user (never a student). Returns a DRAFT only; nothing is
 * persisted until the trainer saves it, so unreviewed AI content never reaches
 * students.
 */
final readonly class GenerateProjectArchitecture
{
    public function __construct(private AiGateway $gateway) {}

    /**
     * @return array{overview: string, layers: list<array{name: string, components: list<array{name: string, role: string}>}>, flows: list<array{from: string, to: string, label: string}>}|null
     */
    public function handle(Lesson $lesson, ProjectSpec $spec, User $actor): ?array
    {
        return app(TenantContext::class)->run($lesson->tenant, function () use ($spec, $actor): ?array {
            $result = $this->gateway->complete(
                $actor,
                AiPurpose::General,
                'project_architecture',
                1,
                [
                    'title' => $spec->title,
                    'summary' => (string) ($spec->summary ?? ''),
                    'tech_stack' => implode(', ', $spec->tech_stack ?? []) ?: 'an appropriate modern stack',
                ],
                ['max_tokens' => 1400],
            );

            return $this->parse($result->text);
        });
    }

    /**
     * Extract + validate the architecture object. Tolerates fences and prose.
     *
     * @return array{overview: string, layers: list<array{name: string, components: list<array{name: string, role: string}>}>, flows: list<array{from: string, to: string, label: string}>}|null
     */
    private function parse(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($decoded)) {
            return null;
        }

        $layers = [];
        foreach ((array) ($decoded['layers'] ?? []) as $layer) {
            if (! is_array($layer) || ! isset($layer['name'])) {
                continue;
            }
            $components = [];
            foreach ((array) ($layer['components'] ?? []) as $c) {
                if (! is_array($c) || ! isset($c['name'])) {
                    continue;
                }
                $components[] = [
                    'name' => trim((string) $c['name']),
                    'role' => trim((string) ($c['role'] ?? '')),
                ];
            }
            if ($components === []) {
                continue;
            }
            $layers[] = ['name' => trim((string) $layer['name']), 'components' => $components];
        }

        if ($layers === []) {
            return null;
        }

        $flows = [];
        foreach ((array) ($decoded['flows'] ?? []) as $f) {
            if (! is_array($f) || ! isset($f['from'], $f['to'])) {
                continue;
            }
            $flows[] = [
                'from' => trim((string) $f['from']),
                'to' => trim((string) $f['to']),
                'label' => trim((string) ($f['label'] ?? '')),
            ];
        }

        return [
            'overview' => trim((string) ($decoded['overview'] ?? '')),
            'layers' => $layers,
            'flows' => $flows,
        ];
    }
}

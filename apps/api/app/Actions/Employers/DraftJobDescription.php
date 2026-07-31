<?php

declare(strict_types=1);

namespace App\Actions\Employers;

use App\Enums\AiPurpose;
use App\Models\EmployerWorkspace;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\JsonOutput;
use App\Support\Roles\RoleTaxonomy;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Draft a JD from a job title (PRD-E F15).
 *
 * A draft, never a publish: the employer always lands in the same form with
 * the text filled in and edits before anything goes live. Writing straight
 * to a published JD would put words in a company's mouth.
 *
 * The taxonomy supplies the skill vocabulary so generated skills match the
 * strings the mock and the talent matcher already use — a JD whose skills
 * are synonyms of ours matches nobody.
 */
final readonly class DraftJobDescription
{
    public function __construct(
        private AiGateway $ai,
        private RoleTaxonomy $taxonomy,
    ) {}

    /**
     * @param  array{notes?: string|null, experience_min_years?: int|null, experience_max_years?: int|null, locations?: list<string>|null}  $context
     * @return array{description: string, skills: list<string>, role_family: string|null, responsibilities: list<string>, must_haves: list<string>, source: string}
     */
    public function handle(User $actor, EmployerWorkspace $workspace, string $title, array $context = []): array
    {
        $title = trim($title);

        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'A job title is required to draft a description.']);
        }

        return app(TenantContext::class)->run($actor->tenant, function () use ($actor, $workspace, $title, $context): array {
            $family = $this->taxonomy->familyForTitle($title);
            $skills = $this->taxonomy->skillsForTitle($title);
            $vocabulary = array_slice([...$skills['core'], ...$skills['optional']], 0, 40);

            $min = $context['experience_min_years'] ?? null;
            $max = $context['experience_max_years'] ?? null;

            try {
                $result = $this->ai->complete($actor, AiPurpose::Content, 'jd_generate', 1, [
                    'title' => $title,
                    'family' => $family['label'] ?? 'unknown',
                    'family_skills' => $vocabulary === [] ? 'none catalogued' : implode(', ', $vocabulary),
                    'notes' => mb_substr((string) ($context['notes'] ?? ''), 0, 1500),
                    'company' => $workspace->name,
                    'experience' => $min === null ? 'not specified' : "{$min}–".($max ?? '+').' years',
                    'locations' => implode(', ', $context['locations'] ?? []) ?: 'not specified',
                ], ['max_tokens' => 1400]);

                $parsed = JsonOutput::object($result->text);
            } catch (Throwable) {
                throw ValidationException::withMessages([
                    'title' => 'Drafting is unavailable right now — write the description yourself and publish as normal.',
                ]);
            }

            if (! is_array($parsed) || ! is_string($parsed['description'] ?? null) || trim($parsed['description']) === '') {
                throw ValidationException::withMessages([
                    'title' => 'Could not draft that description. Try a more specific job title, or write it yourself.',
                ]);
            }

            return [
                'description' => trim($parsed['description']),
                // Keep skills lowercase and de-duplicated so they line up with
                // the taxonomy the matcher and mock read from.
                'skills' => $this->normaliseSkills($parsed['skills'] ?? []),
                'role_family' => $family['key'] ?? (is_string($parsed['role_family'] ?? null) ? $parsed['role_family'] : null),
                'responsibilities' => $this->strings($parsed['responsibilities'] ?? [], 8),
                'must_haves' => $this->strings($parsed['must_haves'] ?? [], 6),
                'source' => 'ai',
            ];
        });
    }

    /**
     * @return list<string>
     */
    private function normaliseSkills(mixed $skills): array
    {
        if (! is_array($skills)) {
            return [];
        }

        $clean = [];
        foreach ($skills as $skill) {
            if (! is_string($skill)) {
                continue;
            }
            $value = mb_strtolower(trim($skill));
            if ($value !== '' && mb_strlen($value) <= 60 && ! in_array($value, $clean, true)) {
                $clean[] = $value;
            }
        }

        return array_slice($clean, 0, 12);
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $items, int $limit): array
    {
        if (! is_array($items)) {
            return [];
        }

        $clean = [];
        foreach ($items as $item) {
            if (is_string($item) && trim($item) !== '') {
                $clean[] = mb_substr(trim($item), 0, 200);
            }
        }

        return array_slice($clean, 0, $limit);
    }
}

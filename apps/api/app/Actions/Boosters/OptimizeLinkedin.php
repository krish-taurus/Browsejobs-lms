<?php

declare(strict_types=1);

namespace App\Actions\Boosters;

use App\Enums\AiPurpose;
use App\Models\CareerBooster;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\JsonOutput;
use App\Support\Cv\CvProfileData;
use Throwable;

/**
 * Career+ LinkedIn profile optimiser (PRD §6.20). Rephrases the candidate's REAL facts
 * (their own profile + verified modules, projects, mock strengths) into a stronger
 * headline, About section, and per-section tips. Like the CV generator, the model may
 * only rephrase — never invent an employer or a title — and the output never names
 * BrowseJobs. AI failure degrades to a deterministic draft from the same facts.
 */
final readonly class OptimizeLinkedin
{
    public function __construct(
        private CvProfileData $profile,
        private AiGateway $gateway,
    ) {}

    public function handle(User $student): CareerBooster
    {
        $facts = $this->profile->for($student);
        [$content, $source] = $this->compose($student, $facts);

        return CareerBooster::query()->updateOrCreate(
            ['user_id' => $student->id, 'kind' => CareerBooster::KIND_LINKEDIN],
            ['tenant_id' => $student->tenant_id, 'content' => $this->scrub($content), 'content_source' => $source],
        );
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function compose(User $student, array $facts): array
    {
        $profile = $facts['profile'];
        $skills = array_values(array_unique([...$facts['completed_modules'], ...array_map(strval(...), (array) $profile['skills'])]));

        try {
            $result = $this->gateway->complete($student, AiPurpose::LinkedinOptimize, 'linkedin_optimize', 1, [
                'role_title' => $facts['role_title'] ?? 'IT professional',
                'skills' => implode(', ', $skills) ?: '(none)',
                'projects' => collect($facts['projects'])->map(fn ($p) => "{$p['name']}: {$p['detail']}")->implode(' | ') ?: '(none)',
                'strengths' => implode(' | ', $facts['mock_strengths']) ?: '(none)',
                'own_summary' => (string) ($profile['summary'] ?? '') ?: '(none)',
                'experience' => collect((array) $profile['experience'])->map(fn ($e) => trim(($e['title'] ?? '').' at '.($e['company'] ?? '')))->filter()->implode(' | ') ?: '(none)',
            ], ['max_tokens' => 900]);

            $decoded = JsonOutput::object($result->text);
            if (is_array($decoded) && isset($decoded['headline'], $decoded['about'])) {
                return [$decoded, 'ai'];
            }
        } catch (Throwable) {
            // fall through
        }

        return [$this->fallback($facts, $skills), 'fallback'];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $skills
     * @return array<string, mixed>
     */
    private function fallback(array $facts, array $skills): array
    {
        $role = $facts['role_title'] ?? 'IT professional';

        return [
            'headline' => trim("{$role} | ".implode(' · ', array_slice($skills, 0, 3)), ' |'),
            'about' => ucfirst($role).' with hands-on experience across '.implode(', ', array_slice($skills, 0, 5))
                .'. Built '.count($facts['projects']).' practical projects with graded, tested code.',
            'top_skills' => array_slice($skills, 0, 12),
            'tips' => [
                'Add the projects below to your Featured section with a one-line result each.',
                'Turn each skill into a concrete outcome in your experience bullets.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function scrub(array $content): array
    {
        array_walk_recursive($content, function (&$v): void {
            if (is_string($v)) {
                $v = trim((string) preg_replace('/\s{2,}/', ' ', str_ireplace(['browsejobs.ai', 'browsejobs', 'browse jobs'], '', $v)), " \t-—·,");
            }
        });

        return $content;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Cv;

use App\Enums\AiPurpose;
use App\Models\CvDocument;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Cv\AtsReport;
use App\Support\Cv\CvProfileData;
use Throwable;

/**
 * Builds one CV version (PRD §6.7) from two fact blocks: the candidate's
 * own claims (uploaded CV + profile edits — theirs to own) and the
 * platform's verified evidence (mastered modules, graded labs, interviewer
 * strengths). The LLM may only rephrase; it never invents, and the finished
 * document NEVER mentions BrowseJobs or course enrolment — enforced in the
 * prompt AND by a deterministic scrub. AI failure degrades to a
 * deterministic assembly. Credit accounting is the caller's job.
 */
final readonly class GenerateCv
{
    public function __construct(
        private CvProfileData $profile,
        private AiGateway $gateway,
        private AtsReport $ats,
    ) {}

    public function handle(User $student, string $source, ?string $jd = null, ?int $courseId = null): CvDocument
    {
        $facts = $this->profile->for($student);
        [$content, $contentSource] = $this->compose($student, $facts, $jd);

        $content = $this->scrubBranding($content);
        $content['name'] = $facts['name'];
        $content['email'] = $facts['email'];
        $content['phone'] = $facts['phone'];
        $content['links'] = $facts['profile']['links'];

        return CvDocument::query()->create([
            'tenant_id' => $student->tenant_id,
            'user_id' => $student->id,
            'course_id' => $courseId,
            'version' => CvDocument::nextVersionFor($student),
            'title' => $facts['role_title'] !== null ? "{$facts['name']} — {$facts['role_title']}" : $facts['name'],
            'source' => $source,
            'content_source' => $contentSource,
            'content' => $content,
            'jd_excerpt' => $jd !== null ? str($jd)->limit(1000)->toString() : null,
            'ats' => $this->ats->for($content, $jd),
            'status' => CvDocument::STATUS_DRAFT,
        ]);
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function compose(User $student, array $facts, ?string $jd): array
    {
        $profile = $facts['profile'];

        try {
            $result = $this->gateway->complete($student, AiPurpose::Cv, 'cv_generate', 2, [
                'name' => $facts['name'],
                'role_title' => $facts['role_title'] ?? 'IT professional',
                'experience' => $this->lines($profile['experience'], fn ($e) => trim(($e['title'] ?? '').' at '.($e['company'] ?? '').' ('.($e['period'] ?? '').'): '.implode('; ', (array) ($e['bullets'] ?? [])))),
                'own_projects' => $this->lines($profile['projects'], fn ($p) => ($p['name'] ?? '').': '.implode('; ', (array) ($p['bullets'] ?? []))),
                'education' => $this->lines($profile['education'], fn ($e) => ($e['name'] ?? '').' — '.($e['detail'] ?? '')),
                'own_skills' => implode(', ', (array) $profile['skills']) ?: '(none)',
                'own_summary' => (string) ($profile['summary'] ?? '') ?: '(none)',
                'certifications' => implode(', ', (array) $profile['certifications']) ?: '(none)',
                'modules' => implode(', ', $facts['completed_modules']) ?: '(none)',
                'lab_projects' => collect($facts['projects'])->map(fn ($p) => "{$p['name']}: {$p['detail']}")->implode(' | ') ?: '(none)',
                'strengths' => implode(' | ', $facts['mock_strengths']) ?: '(none)',
                'jd' => $jd !== null && trim($jd) !== '' ? mb_substr($jd, 0, 6000) : '(none)',
            ], ['max_tokens' => 2200]);

            $decoded = json_decode(trim($result->text), true);

            if (is_array($decoded) && ! empty($decoded['skills']) && isset($decoded['summary'])) {
                return [$decoded, 'ai'];
            }
        } catch (Throwable) {
            // Budget/transport failure — deterministic assembly below.
        }

        return [$this->fallback($facts), 'fallback'];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function lines(array $items, callable $format): string
    {
        $out = collect($items)->map($format)->filter(fn ($l) => trim((string) $l, ' :—-') !== '')->implode(' | ');

        return $out !== '' ? $out : '(none)';
    }

    /**
     * The finished document never says who trained the candidate: strip any
     * BrowseJobs mention the model let slip, then drop entries left empty.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function scrubBranding(array $content): array
    {
        array_walk_recursive($content, function (&$value): void {
            if (is_string($value)) {
                $value = trim((string) preg_replace(
                    '/\s{2,}/',
                    ' ',
                    str_ireplace(['browsejobs.ai', 'browsejobs', 'browse jobs'], '', $value),
                ), " \t\-—·,");
            }
        });

        foreach (['certifications', 'skills'] as $section) {
            if (isset($content[$section]) && is_array($content[$section])) {
                $content[$section] = collect($content[$section])
                    ->reject(fn ($item) => is_string($item) && trim($item) === '')
                    ->values()
                    ->all();
            }
        }

        // Education without an institution left after the scrub was a
        // course-as-education entry — a real degree keeps its university.
        if (isset($content['education']) && is_array($content['education'])) {
            $content['education'] = collect($content['education'])
                ->reject(fn ($e) => trim((string) ($e['name'] ?? '')) === '' || trim((string) ($e['detail'] ?? '')) === '')
                ->values()
                ->all();
        }

        return $content;
    }

    /**
     * The same facts, assembled without prose flair — never a dead end, and
     * just as brand-silent as the AI path.
     *
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    private function fallback(array $facts): array
    {
        $profile = $facts['profile'];
        $role = $facts['role_title'] ?? 'IT professional';
        $skills = array_values(array_unique([
            ...$facts['completed_modules'],
            ...array_map(strval(...), (array) $profile['skills']),
        ]));

        $projects = [
            ...collect($facts['projects'])->map(fn ($p) => ['name' => $p['name'], 'bullets' => [$p['detail']]])->all(),
            ...(array) $profile['projects'],
        ];

        return [
            'headline' => trim("{$role} — ".implode(', ', array_slice($skills, 0, 3)), ' —'),
            'summary' => (string) ($profile['summary']
                ?? ucfirst($role).' candidate with '.count($skills).' core skill areas and '
                    .count($projects).' hands-on projects, backed by graded practical work.'),
            'skills' => array_slice($skills, 0, 14),
            'experience' => (array) $profile['experience'],
            'projects' => $projects,
            'education' => (array) $profile['education'],
            'certifications' => (array) $profile['certifications'],
        ];
    }
}

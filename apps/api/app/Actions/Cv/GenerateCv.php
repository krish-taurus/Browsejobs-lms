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
 * Builds one CV version (PRD §6.7) from the student's real record. The
 * facts come from CvProfileData (the compliance boundary — never-invent);
 * the LLM writes the prose; any AI failure degrades to a deterministic
 * assembly of the same facts, so a CV always exists. Credit accounting is
 * the CALLER's job — the auto build is free, manual/tailored builds consume.
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

        $content['name'] = $facts['name'];
        $content['email'] = $facts['email'];
        $content['phone'] = $facts['phone'];

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
        try {
            $result = $this->gateway->complete($student, AiPurpose::Cv, 'cv_generate', 1, [
                'name' => $facts['name'],
                'role_title' => $facts['role_title'] ?? 'IT professional',
                'courses' => implode(', ', $facts['courses']) ?: '(none)',
                'modules' => implode(', ', $facts['completed_modules']) ?: '(none)',
                'projects' => collect($facts['projects'])->map(fn ($p) => "{$p['name']}: {$p['detail']}")->implode(' | ') ?: '(none)',
                'certifications' => implode(', ', $facts['certifications']) ?: '(none)',
                'strengths' => implode(' | ', $facts['mock_strengths']) ?: '(none)',
                'jd' => $jd !== null && trim($jd) !== '' ? mb_substr($jd, 0, 6000) : '(none)',
            ], ['max_tokens' => 1800]);

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
     * The same facts, assembled without prose flair — never a dead end.
     *
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    private function fallback(array $facts): array
    {
        $role = $facts['role_title'] ?? 'IT professional';

        return [
            'headline' => trim("{$role} in training — ".implode(', ', array_slice($facts['courses'], 0, 2)), ' —'),
            'summary' => ucfirst($role).' candidate with '.count($facts['completed_modules']).' completed modules and '
                .count($facts['projects']).' graded lab projects at BrowseJobs.',
            'skills' => array_slice($facts['completed_modules'], 0, 14),
            'projects' => collect($facts['projects'])->map(fn ($p) => [
                'name' => $p['name'],
                'bullets' => [$p['detail']],
            ])->all(),
            'education' => collect($facts['courses'])->map(fn ($c) => [
                'name' => $c, 'detail' => 'BrowseJobs — industry-aligned bootcamp',
            ])->all(),
            'certifications' => $facts['certifications'],
        ];
    }
}

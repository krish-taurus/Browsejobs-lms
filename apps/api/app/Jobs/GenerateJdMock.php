<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Enums\JdMockStatus;
use App\Events\JdMockGenerated;
use App\Models\EmployerJob;
use App\Models\JdMock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\JsonOutput;
use App\Support\Employers\MockDesign;
use App\Support\Roles\RoleTaxonomy;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Generates one jd_mocks version (questions + rubric) via AiGateway
 * (PRD-E F2). Idempotent: only a `pending` row is processed — re-runs on
 * an already-ready/failed version are no-ops. Fail-safe: on AI failure or
 * malformed JSON it degrades to a deterministic skill-template mock so a
 * published JD always becomes applicable (source = fallback).
 */
final class GenerateJdMock implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function __construct(public readonly int $jdMockId) {}

    public function handle(AiGateway $gateway): void
    {
        $mock = JdMock::withoutGlobalScopes()->find($this->jdMockId);
        if ($mock === null || $mock->status !== JdMockStatus::Pending) {
            return;
        }

        $tenant = Tenant::query()->find($mock->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($gateway, $mock): void {
            $job = $mock->job()->withoutGlobalScopes()->first();
            if ($job === null) {
                return;
            }

            $mock->update(['status' => JdMockStatus::Generating->value]);

            $actor = User::query()->find($job->created_by_id);
            $generated = $actor !== null ? $this->viaAi($gateway, $actor, $job) : null;
            $source = $generated !== null ? 'ai' : 'fallback';
            $generated ??= $this->fallback($job);

            $mock->update([
                'status' => JdMockStatus::Ready->value,
                'source' => $source,
                'questions' => $generated['questions'],
                'rubric' => $generated['rubric'],
                'generated_at' => now(),
            ]);

            JdMockGenerated::dispatch($mock->fresh());
        });
    }

    /** @return array{questions: array<int, array<string, mixed>>, rubric: array<string, mixed>}|null */
    private function viaAi(AiGateway $gateway, User $actor, EmployerJob $job): ?array
    {
        try {
            $result = $gateway->complete($actor, AiPurpose::JdMockGen, 'jd_mock_gen', 1, [
                'title' => $job->title,
                'role_family' => $job->role_family ?? 'unspecified',
                'experience_min_years' => (string) $job->experience_min_years,
                'experience_max_years' => (string) ($job->experience_max_years ?? '10+'),
                'skills' => implode(', ', $job->skills ?? []),
                'description' => mb_substr($job->description, 0, 8000),
                // The employer's design, when they set one. Empty string
                // leaves the prompt exactly as it was for undesigned JDs.
                'design' => MockDesign::fromArray($job->mock_design, app(RoleTaxonomy::class))->toPromptInstruction(),
            ], ['max_tokens' => 2500]);

            $data = JsonOutput::object($result->text);
            if (! is_array($data) || ! is_array($data['questions'] ?? null) || ! is_array($data['rubric'] ?? null)) {
                return null;
            }

            $questions = array_values(array_filter($data['questions'], fn ($q): bool => is_array($q) && is_string($q['text'] ?? null) && $q['text'] !== ''));
            $dimensions = array_values(array_filter($data['rubric']['dimensions'] ?? [], fn ($d): bool => is_array($d) && is_string($d['key'] ?? null)));

            if (count($questions) < 5 || count($dimensions) < 3) {
                return null;
            }

            return [
                'questions' => $questions,
                'rubric' => ['dimensions' => $dimensions],
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Deterministic degrade: one scenario question per JD skill plus role
     * staples, with a standard rubric. Guarantees every published JD is
     * applicable-to even when AI is down or over budget.
     *
     * @return array{questions: array<int, array<string, mixed>>, rubric: array<string, mixed>}
     */
    private function fallback(EmployerJob $job): array
    {
        $questions = [];
        $id = 1;

        foreach (array_slice($job->skills ?? [], 0, 8) as $skill) {
            $questions[] = [
                'id' => $id++,
                'text' => "Describe a piece of work where you used {$skill}. What was the problem, what did you do, and how did you know it worked?",
                'skill' => mb_strtolower((string) $skill),
                'type' => 'scenario',
                'weight' => 1,
            ];
        }

        $questions[] = [
            'id' => $id++,
            'text' => "Walk me through how you would approach your first month as a {$job->title}.",
            'skill' => 'role_fit',
            'type' => 'behavioral',
            'weight' => 1,
        ];
        $questions[] = [
            'id' => $id,
            'text' => 'Tell me about a time something you built or worked on failed. What happened and what did you change afterwards?',
            'skill' => 'ownership',
            'type' => 'behavioral',
            'weight' => 1,
        ];

        return [
            'questions' => $questions,
            'rubric' => [
                'dimensions' => [
                    ['key' => 'technical_depth', 'label' => 'Technical depth', 'weight' => 40, 'criteria' => 'Concrete, correct detail on the skills the JD names.'],
                    ['key' => 'problem_solving', 'label' => 'Problem solving', 'weight' => 25, 'criteria' => 'Structured approach: clarifies, decomposes, weighs trade-offs.'],
                    ['key' => 'experience_evidence', 'label' => 'Evidence of experience', 'weight' => 20, 'criteria' => 'Real examples with outcomes, not generalities.'],
                    ['key' => 'communication', 'label' => 'Communication', 'weight' => 15, 'criteria' => 'Clear, concise verbal explanations.'],
                ],
            ],
        ];
    }
}

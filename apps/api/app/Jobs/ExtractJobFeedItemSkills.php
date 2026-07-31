<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Models\JobFeedItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\JsonOutput;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * AI skill extraction for one feed item (PRD §6.22 → §6.21 pipeline): reuses the
 * `jd_extract` prompt to fill extracted_skills / seniority / quality. Same
 * fail-safe as the market-JD extractor — a keyword scan when AI is unavailable,
 * so every item is still matchable. Idempotent; billed to a staff user.
 */
final class ExtractJobFeedItemSkills implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function __construct(public readonly int $itemId) {}

    public function handle(AiGateway $gateway): void
    {
        $item = JobFeedItem::query()->withoutGlobalScopes()->find($this->itemId);
        if ($item === null) {
            return;
        }

        $tenant = Tenant::query()->find($item->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($gateway, $item): void {
            $actor = $this->billingActor((int) $item->tenant_id);
            $parsed = $actor !== null ? $this->viaAi($gateway, $actor, $item) : null;
            $parsed ??= $this->fallback($item);

            $item->update([
                'extracted_skills' => $parsed['skills'],
                'seniority' => $item->seniority ?? $parsed['seniority'],
                'quality_score' => $parsed['quality_score'],
            ]);
        });
    }

    /**
     * @return array{skills: list<string>, seniority: string|null, quality_score: int}|null
     */
    private function viaAi(AiGateway $gateway, User $actor, JobFeedItem $item): ?array
    {
        try {
            $result = $gateway->complete($actor, AiPurpose::JdExtract, 'jd_extract', 1, [
                'role_hint' => (string) ($item->role_title ?? $item->title),
                'jd' => mb_substr($item->description, 0, 8000),
            ], ['max_tokens' => 700]);

            $data = JsonOutput::object($result->text);
            if (! is_array($data) || ! is_array($data['skills'] ?? null)) {
                return null;
            }

            $skills = $this->normalizeSkills($data['skills']);
            if ($skills === []) {
                return null;
            }

            return [
                'skills' => $skills,
                'seniority' => $this->cleanSeniority($data['seniority'] ?? null),
                'quality_score' => max(0, min(100, (int) ($data['quality_score'] ?? 0))),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{skills: list<string>, seniority: string|null, quality_score: int}
     */
    private function fallback(JobFeedItem $item): array
    {
        $haystack = mb_strtolower($item->description);
        $found = array_values(array_filter(self::SKILL_VOCAB, fn ($s) => str_contains($haystack, $s)));

        return [
            'skills' => $found,
            'seniority' => null,
            'quality_score' => $found === [] ? 10 : min(60, 20 + count($found) * 5),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return list<string>
     */
    private function normalizeSkills(array $raw): array
    {
        $skills = [];
        foreach ($raw as $item) {
            $skill = mb_strtolower(trim((string) $item));
            if ($skill !== '' && mb_strlen($skill) <= 40) {
                $skills[] = $skill;
            }
        }

        return array_values(array_slice(array_unique($skills), 0, 20));
    }

    private function cleanSeniority(mixed $value): ?string
    {
        $v = is_string($value) ? mb_strtolower(trim($value)) : '';

        return in_array($v, ['fresher', 'junior', 'mid', 'senior'], true) ? $v : null;
    }

    private function billingActor(int $tenantId): ?User
    {
        return User::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_type', 'staff')->orderBy('id')->first();
    }

    /** @var list<string> */
    private const SKILL_VOCAB = [
        'python', 'sql', 'java', 'scala', 'spark', 'pyspark', 'hadoop', 'airflow', 'dbt', 'kafka',
        'snowflake', 'redshift', 'bigquery', 'databricks', 'etl', 'data modeling', 'aws', 'azure',
        'gcp', 'docker', 'kubernetes', 'terraform', 'linux', 'git', 'power bi', 'tableau', 'excel',
        'pandas', 'numpy', 'machine learning', 'nosql', 'mongodb', 'postgres', 'mysql', 'rest',
        'api', 'ci/cd', 'jenkins', 'selenium', 'spring boot',
    ];
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Models\MarketJd;
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
 * AI parse stage for one market JD (PRD §6.21): JD text → canonical skill list +
 * seniority/location/quality. Idempotent and retry-safe — re-running simply
 * re-parses the same row. Fail-safe: on a budget/transport failure or malformed
 * JSON it degrades to a deterministic keyword scan so a JD still contributes a
 * skill signal (never an error, never zero data). Billed to a resolved staff
 * user, never a student.
 */
final class ExtractJdSkills implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function __construct(public readonly int $marketJdId) {}

    public function handle(AiGateway $gateway): void
    {
        $jd = MarketJd::query()->withoutGlobalScopes()->find($this->marketJdId);
        if ($jd === null) {
            return;
        }

        $tenant = Tenant::query()->find($jd->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($gateway, $jd): void {
            $actor = $this->billingActor((int) $jd->tenant_id);

            $parsed = $actor !== null ? $this->viaAi($gateway, $actor, $jd) : null;
            if ($parsed === null) {
                $parsed = $this->fallback($jd);
            }

            $jd->update([
                'extracted_skills' => $parsed['skills'],
                'seniority' => $jd->seniority ?? $parsed['seniority'],
                'location' => $jd->location ?? $parsed['location'],
                'quality_score' => $parsed['quality_score'],
                'parse_status' => MarketJd::STATUS_PARSED,
                'parsed_at' => now(),
            ]);
        });
    }

    /**
     * @return array{skills: list<string>, seniority: string|null, location: string|null, quality_score: int}|null
     */
    private function viaAi(AiGateway $gateway, User $actor, MarketJd $jd): ?array
    {
        try {
            $result = $gateway->complete($actor, AiPurpose::JdExtract, 'jd_extract', 1, [
                'role_hint' => $jd->role_title,
                'jd' => mb_substr($jd->raw_jd, 0, 8000),
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
                'location' => $this->cleanScalar($data['location'] ?? null),
                'quality_score' => $this->clampScore($data['quality_score'] ?? 0),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Deterministic degrade: scan the JD for a known skill vocabulary. Guarantees
     * a JD still counts toward demand trends when AI is unavailable.
     *
     * @return array{skills: list<string>, seniority: string|null, location: string|null, quality_score: int}
     */
    private function fallback(MarketJd $jd): array
    {
        $haystack = mb_strtolower($jd->raw_jd);
        $found = [];
        foreach (self::SKILL_VOCAB as $skill) {
            if (str_contains($haystack, $skill)) {
                $found[] = $skill;
            }
        }

        return [
            'skills' => array_values(array_unique($found)),
            'seniority' => null,
            'location' => null,
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

    private function cleanScalar(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $v = trim($value);

        return ($v === '' || mb_strtolower($v) === 'null') ? null : mb_substr($v, 0, 120);
    }

    private function clampScore(mixed $value): int
    {
        return max(0, min(100, (int) $value));
    }

    /** A staff user in the tenant to bill the parse to (never a student). */
    private function billingActor(int $tenantId): ?User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_type', 'staff')
            ->orderBy('id')
            ->first();
    }

    /**
     * Minimal skill vocabulary for the deterministic fallback. Not exhaustive —
     * the AI path is primary; this only guarantees a non-empty signal offline.
     *
     * @var list<string>
     */
    private const SKILL_VOCAB = [
        'python', 'sql', 'java', 'scala', 'spark', 'pyspark', 'hadoop', 'airflow',
        'dbt', 'kafka', 'snowflake', 'redshift', 'bigquery', 'databricks', 'etl',
        'data modeling', 'data warehouse', 'aws', 'azure', 'gcp', 'docker',
        'kubernetes', 'terraform', 'linux', 'git', 'power bi', 'tableau', 'excel',
        'pandas', 'numpy', 'machine learning', 'nosql', 'mongodb', 'postgres',
        'mysql', 'rest', 'api', 'ci/cd', 'jenkins', 'ansible',
    ];
}

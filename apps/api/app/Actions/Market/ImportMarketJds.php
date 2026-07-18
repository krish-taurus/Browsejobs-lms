<?php

declare(strict_types=1);

namespace App\Actions\Market;

use App\Jobs\ExtractJdSkills;
use App\Models\MarketJd;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Ingests a batch of job-market JDs (PRD §6.21). Placement-team owned — these are
 * imported exports/manual entries, never scraped. Dedupes on a role+body
 * fingerprint so re-imports don't inflate demand counts, then queues the AI skill
 * parse per new row. Returns a small summary for the caller.
 */
final readonly class ImportMarketJds
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  list<array{role_title: string, raw_jd: string, source?: string, company?: string|null, location?: string|null, seniority?: string|null, course_id?: int|null, external_ref?: string|null}>  $rows
     * @return array{imported: int, duplicates: int, skipped: int}
     */
    public function handle(User $actor, array $rows): array
    {
        $imported = 0;
        $duplicates = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $role = trim((string) ($row['role_title'] ?? ''));
            $jd = trim((string) ($row['raw_jd'] ?? ''));

            if ($role === '' || mb_strlen($jd) < 40) {
                $skipped++; // too thin to be a market signal

                continue;
            }

            $fingerprint = MarketJd::fingerprintFor($role, $jd);

            $created = DB::transaction(function () use ($actor, $row, $role, $jd, $fingerprint): ?MarketJd {
                $exists = MarketJd::query()
                    ->where('tenant_id', $actor->tenant_id)
                    ->where('fingerprint', $fingerprint)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    return null;
                }

                return MarketJd::query()->create([
                    'tenant_id' => $actor->tenant_id,
                    'course_id' => $row['course_id'] ?? null,
                    'source' => $this->cleanSource($row['source'] ?? 'manual'),
                    'external_ref' => isset($row['external_ref']) ? mb_substr((string) $row['external_ref'], 0, 255) : null,
                    'role_title' => mb_substr($role, 0, 255),
                    'company' => isset($row['company']) ? mb_substr((string) $row['company'], 0, 255) : null,
                    'location' => isset($row['location']) ? mb_substr((string) $row['location'], 0, 255) : null,
                    'seniority' => $this->cleanSeniority($row['seniority'] ?? null),
                    'raw_jd' => $jd,
                    'fingerprint' => $fingerprint,
                    'parse_status' => MarketJd::STATUS_PENDING,
                    'ingested_at' => now(),
                ]);
            });

            if ($created === null) {
                $duplicates++;

                continue;
            }

            ExtractJdSkills::dispatch($created->id);
            $imported++;
        }

        $this->audit->log('market_jd.imported', null, [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
        ], $actor);

        return ['imported' => $imported, 'duplicates' => $duplicates, 'skipped' => $skipped];
    }

    private function cleanSource(mixed $value): string
    {
        $v = is_string($value) ? mb_strtolower(trim($value)) : 'manual';

        return in_array($v, ['manual', 'csv', 'partner', 'api'], true) ? $v : 'manual';
    }

    private function cleanSeniority(mixed $value): ?string
    {
        $v = is_string($value) ? mb_strtolower(trim($value)) : '';

        return in_array($v, ['fresher', 'junior', 'mid', 'senior'], true) ? $v : null;
    }
}

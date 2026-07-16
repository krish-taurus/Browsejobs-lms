<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Models\Lead;
use App\Models\Tenant;
use App\Support\Crm\PhoneNormalizer;

/**
 * Bulk lead intake from CSV (PRD §6.12 capture source). Header-aware: recognises
 * name, phone, email, lead_type, course_slug, utm_source columns. Each valid row
 * routes through CaptureLead (stage/assign/score/SLA). Rows whose normalized
 * phone already exists in the tenant are counted as duplicates and skipped so
 * the import stays idempotent; the merge tool handles intentional folds.
 *
 * @phpstan-type ImportSummary array{imported: int, duplicates: int, skipped: list<array{row: int, reason: string}>}
 */
final readonly class ImportLeadsCsv
{
    public function __construct(private CaptureLead $capture) {}

    /**
     * @return ImportSummary
     */
    public function handle(Tenant $tenant, string $csv): array
    {
        $rows = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $csv) ?: [])));

        if ($rows === []) {
            return ['imported' => 0, 'duplicates' => 0, 'skipped' => []];
        }

        $header = array_map(fn (string $h) => strtolower(trim($h)), explode(',', array_shift($rows) ?? ''));

        $imported = 0;
        $duplicates = 0;
        $skipped = [];

        foreach ($rows as $index => $line) {
            $rowNumber = $index + 1;
            $cells = array_map('trim', explode(',', $line));
            /** @var array<string, string> $row */
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = $cells[$i] ?? '';
            }

            $name = $row['name'] ?? '';
            $phone = $row['phone'] ?? '';

            if ($name === '' || $phone === '') {
                $skipped[] = ['row' => $rowNumber, 'reason' => 'Missing name or phone.'];

                continue;
            }

            $leadType = ($row['lead_type'] ?? '') !== '' ? $row['lead_type'] : 'contact';
            if (! in_array($leadType, Lead::TYPES, true)) {
                $skipped[] = ['row' => $rowNumber, 'reason' => "Invalid lead_type '{$leadType}'."];

                continue;
            }

            $normalized = PhoneNormalizer::normalize($phone);
            $isDuplicate = Lead::query()
                ->where('tenant_id', $tenant->id)
                ->where('phone_normalized', $normalized)
                ->whereNull('merged_into_id')
                ->exists();

            if ($isDuplicate) {
                $duplicates++;

                continue;
            }

            $this->capture->handle($tenant, [
                'lead_type' => $leadType,
                'name' => $name,
                'phone' => $phone,
                'email' => ($row['email'] ?? '') !== '' ? $row['email'] : null,
                'course_slug' => ($row['course_slug'] ?? '') !== '' ? $row['course_slug'] : null,
                'utm_source' => ($row['utm_source'] ?? '') !== '' ? $row['utm_source'] : 'csv_import',
                'page' => 'csv_import',
                'consent_version' => 'import',
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'duplicates' => $duplicates, 'skipped' => $skipped];
    }
}

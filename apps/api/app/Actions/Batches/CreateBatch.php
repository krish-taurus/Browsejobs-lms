<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Enums\BatchType;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Scopes\TenantScope;
use Illuminate\Validation\ValidationException;

/**
 * Creates a batch, generating its number as {COURSE}-{YYYYMM}-{seq} unless a
 * manual number is supplied. Uniqueness is enforced per tenant at the DB level;
 * the generator also skips any already-taken sequence.
 */
final readonly class CreateBatch
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Course $course, BatchType $type, array $attributes = [], ?string $manualNumber = null): Batch
    {
        $number = $manualNumber !== null && $manualNumber !== ''
            ? $manualNumber
            : $this->generateNumber($course);

        if ($this->numberExists($course->tenant_id, $number)) {
            throw ValidationException::withMessages([
                'number' => "Batch number {$number} is already in use.",
            ]);
        }

        return Batch::query()->create([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'number' => $number,
            'type' => $type->value,
            'capacity' => $attributes['capacity'] ?? null,
            'starts_on' => $attributes['starts_on'] ?? null,
            'ends_on' => $attributes['ends_on'] ?? null,
            'linked_source_batch_id' => $attributes['linked_source_batch_id'] ?? null,
        ]);
    }

    /** Cohort numbers begin here: {COURSE}-{YYYYMM}-100. */
    private const FIRST_SEQUENCE = 100;

    private function generateNumber(Course $course): string
    {
        $prefix = strtoupper($course->code).'-'.now()->format('Ym').'-';

        // The trailing sequence runs across every month this course has ever
        // had a batch in — not reset back to 100 the moment the calendar
        // rolls into a new one. Counting only this month's own prefix used
        // to mean a course's very first batch in October and its fifth
        // batch overall both landed on "-100", which reads as a duplicate
        // even though the numbers as full strings differ (the month differs).
        // The month still appears in the prefix — it just no longer resets
        // the count.
        $maxSeq = self::FIRST_SEQUENCE - 1;

        Batch::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $course->tenant_id)
            ->where('number', 'like', strtoupper($course->code).'-%')
            ->pluck('number')
            ->each(function (string $number) use (&$maxSeq): void {
                if (preg_match('/-(\d+)$/', $number, $m) === 1) {
                    $maxSeq = max($maxSeq, (int) $m[1]);
                }
            });

        do {
            $maxSeq++;
            $candidate = $prefix.str_pad((string) $maxSeq, 3, '0', STR_PAD_LEFT);
        } while ($this->numberExists($course->tenant_id, $candidate));

        return $candidate;
    }

    private function numberExists(?int $tenantId, string $number): bool
    {
        return Batch::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('number', $number)
            ->exists();
    }
}

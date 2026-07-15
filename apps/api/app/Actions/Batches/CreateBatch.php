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

    private function generateNumber(Course $course): string
    {
        $prefix = strtoupper($course->code).'-'.now()->format('Ym').'-';

        $seq = Batch::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $course->tenant_id)
            ->where('number', 'like', $prefix.'%')
            ->count();

        do {
            $seq++;
            $candidate = $prefix.str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
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

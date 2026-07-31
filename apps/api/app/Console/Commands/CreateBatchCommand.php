<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Batches\CreateBatch;
use App\Console\Commands\Concerns\ResolvesCrmTargets;
use App\Enums\BatchType;
use App\Models\Course;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Creates a batch of any type for a course — driven by the CRM's Live Batches
 * page (the funnel automation creates its own batches; this is for manual
 * cohorts, e.g. a paid batch opened outside the weekend cycle).
 */
final class CreateBatchCommand extends Command
{
    use ResolvesCrmTargets;

    protected $signature = 'batch:create
        {course : Course slug}
        {type : Batch type (masterclass|bootcamp|paid|self_paced)}
        {--starts= : Start date (Y-m-d)}
        {--ends= : End date (Y-m-d)}
        {--capacity= : Seat cap}
        {--number= : Manual batch number (defaults to COURSE-YYYYMM-seq)}';

    protected $description = 'Create a batch for a course (any type), with an auto-generated number';

    public function handle(CreateBatch $createBatch): int
    {
        $course = Course::query()->withoutGlobalScope(TenantScope::class)
            ->where('slug', $this->argument('course'))
            ->first();

        if ($course === null) {
            $this->error("Course '{$this->argument('course')}' not found.");

            return self::FAILURE;
        }

        $type = BatchType::tryFrom((string) $this->argument('type'));

        if ($type === null) {
            $this->error("Unknown batch type '{$this->argument('type')}'.");

            return self::FAILURE;
        }

        return $this->runForTenant($course->tenant_id, function () use ($course, $type, $createBatch): int {
            try {
                $batch = $createBatch->handle($course, $type, [
                    'starts_on' => $this->option('starts') ?: null,
                    'ends_on' => $this->option('ends') ?: null,
                    'capacity' => $this->option('capacity') !== null ? (int) $this->option('capacity') : null,
                ], $this->option('number') ?: null);
            } catch (ValidationException $e) {
                $this->error(collect($e->errors())->flatten()->implode(' '));

                return self::FAILURE;
            }

            $this->info("Created {$type->value} batch {$batch->number} for {$course->name}.");

            return self::SUCCESS;
        });
    }
}

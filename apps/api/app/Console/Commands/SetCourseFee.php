<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;

/**
 * Set (or clear) a course's registration fee, driven from the CRM.
 *
 * The CRM used to write `courses.fee_paise` straight down its `lms` connection,
 * which could never work: that database user is read-only everywhere except
 * `leads`, so every Save silently failed with an access-denied query error.
 * Every other CRM-driven change goes through a command; this one now does too.
 *
 * Clearing the fee falls the course back to the platform default in
 * config/fees.php. Existing fee plans keep the amount they were agreed at — a
 * price change never rewrites a schedule a student already signed up to.
 */
final class SetCourseFee extends Command
{
    protected $signature = 'course:set-fee
        {course : course id or slug}
        {--rupees= : fee in whole rupees}
        {--clear : fall back to the platform default}';

    protected $description = 'Set or clear a course registration fee';

    public function handle(): int
    {
        $reference = (string) $this->argument('course');

        $course = Course::query()->withoutGlobalScope(TenantScope::class)
            ->when(
                ctype_digit($reference),
                fn ($q) => $q->whereKey((int) $reference),
                fn ($q) => $q->where('slug', $reference),
            )
            ->first();

        if ($course === null) {
            $this->error("Course not found: {$reference}");

            return self::FAILURE;
        }

        if ($this->option('clear')) {
            $course->forceFill(['fee_paise' => null])->save();
            $this->line((string) json_encode([
                'course' => $course->name,
                'fee_paise' => null,
                'using' => 'platform default',
            ], JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $rupees = $this->option('rupees');

        if ($rupees === null || ! is_numeric($rupees) || (float) $rupees < 0) {
            $this->error('Pass --rupees with a positive amount, or --clear.');

            return self::FAILURE;
        }

        $course->forceFill(['fee_paise' => (int) round(((float) $rupees) * 100)])->save();

        $this->line((string) json_encode([
            'course' => $course->name,
            'fee_paise' => $course->fee_paise,
            'fee_rupees' => (int) round($course->fee_paise / 100),
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}

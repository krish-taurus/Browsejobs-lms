<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CoursePrice;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;

/**
 * Set (or clear) what a course costs outside India, driven from the CRM.
 *
 * The sibling of course:set-fee, which owns the rupee price. India is never
 * touched here: clearing an international price simply leaves those students
 * with no published price, it does not fall back to the rupee amount, because
 * quietly charging ₹30,000 to a card abroad is not the same offer.
 *
 * Amounts are given in the major unit (dollars) and stored in the minor unit
 * (cents), so money never passes through a float.
 */
final class SetCoursePrice extends Command
{
    protected $signature = 'course:set-price
        {course : course id or slug}
        {--region=INTL : INTL for everywhere outside India, or an ISO country code}
        {--currency=USD : ISO currency code}
        {--amount= : price in the major unit, e.g. 599 for $599}
        {--emi-count= : number of instalments, omit for single payment}
        {--emi-amount= : one instalment in the major unit}
        {--clear : remove this price}';

    protected $description = 'Set or clear a course price for students outside India';

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

        $region = strtoupper(trim((string) $this->option('region'))) ?: CoursePrice::REGION_INTERNATIONAL;

        if ($this->option('clear')) {
            CoursePrice::query()->where('course_id', $course->id)->where('region', $region)->delete();

            $this->line((string) json_encode([
                'course' => $course->name,
                'region' => $region,
                'price' => null,
            ], JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $amount = $this->option('amount');

        if ($amount === null || ! is_numeric($amount) || (float) $amount <= 0) {
            $this->error('Pass --amount with a positive amount, or --clear.');

            return self::FAILURE;
        }

        $currency = strtoupper(trim((string) $this->option('currency')));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $this->error('--currency must be a 3-letter code such as USD.');

            return self::FAILURE;
        }

        $emiCount = $this->option('emi-count');
        $emiAmount = $this->option('emi-amount');

        if ($emiCount !== null && $emiCount !== '' && (! ctype_digit((string) $emiCount) || (int) $emiCount < 1)) {
            $this->error('--emi-count must be a whole number of instalments.');

            return self::FAILURE;
        }

        $hasEmi = $emiCount !== null && $emiCount !== '';

        if ($hasEmi && ($emiAmount === null || $emiAmount === '' || ! is_numeric($emiAmount) || (float) $emiAmount <= 0)) {
            $this->error('--emi-amount is required when --emi-count is given.');

            return self::FAILURE;
        }

        $price = CoursePrice::query()->updateOrCreate(
            ['course_id' => $course->id, 'region' => $region],
            [
                'currency' => $currency,
                'amount_minor' => (int) round(((float) $amount) * 100),
                'emi_count' => $hasEmi ? (int) $emiCount : null,
                'emi_amount_minor' => $hasEmi ? (int) round(((float) $emiAmount) * 100) : null,
            ],
        );

        $this->line((string) json_encode([
            'course' => $course->name,
            'region' => $price->region,
            'currency' => $price->currency,
            'amount' => $price->amount(),
            'emi_count' => $price->emi_count,
            'emi_amount' => $price->emiAmount(),
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}

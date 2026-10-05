<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A course's price for students outside India. India stays on
 * `courses.fee_paise`; see the create_course_prices_table migration.
 */
class CoursePrice extends Model
{
    /** Everyone outside India, until per-country rows are wanted. */
    public const REGION_INTERNATIONAL = 'INTL';

    protected $fillable = [
        'course_id',
        'region',
        'currency',
        'amount_minor',
        'emi_count',
        'emi_amount_minor',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'emi_count' => 'integer',
            'emi_amount_minor' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** The price as a decimal in the major unit, e.g. 599.00 for 59900 cents. */
    public function amount(): float
    {
        return $this->amount_minor / 100;
    }

    /** One instalment in the major unit, or null when there is no EMI plan. */
    public function emiAmount(): ?float
    {
        return $this->emi_amount_minor === null ? null : $this->emi_amount_minor / 100;
    }
}

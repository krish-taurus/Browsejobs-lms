<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A curated salary benchmark row (PRD §6.21): the 25th/50th/75th-percentile
 * annual CTC (paise) for a role, city, and experience band. Reference data for
 * offer-evaluation guidance.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $course_id
 * @property string $role_title
 * @property string $city
 * @property string $experience_band
 * @property int $p25_ctc_paise
 * @property int $p50_ctc_paise
 * @property int $p75_ctc_paise
 * @property string|null $source
 * @property Carbon|null $effective_on
 */
class SalaryBenchmark extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const BANDS = ['fresher', '1-3', '3-5', '5plus'];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'course_id', 'role_title', 'city', 'experience_band',
        'p25_ctc_paise', 'p50_ctc_paise', 'p75_ctc_paise', 'source', 'effective_on',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'p25_ctc_paise' => 'integer',
            'p50_ctc_paise' => 'integer',
            'p75_ctc_paise' => 'integer',
            'effective_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}

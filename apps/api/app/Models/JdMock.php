<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JdMockStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\JdMockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Versioned JD-specific mock (questions + rubric) — PRD-E F2. A version
 * is immutable once ready so candidate scores stay comparable; changes
 * create the next version instead.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_job_id
 * @property int $version
 * @property JdMockStatus $status
 * @property string|null $source
 * @property array<string, mixed>|null $questions
 * @property array<string, mixed>|null $rubric
 * @property Carbon|null $generated_at
 */
final class JdMock extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<JdMockFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'employer_job_id',
        'version',
        'status',
        'source',
        'questions',
        'rubric',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => JdMockStatus::class,
            'questions' => 'array',
            'rubric' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EmployerJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(EmployerJob::class, 'employer_job_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployerJobStatus;
use App\Enums\JdMockStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Employer-owned JD (PRD-E F2, ADR 0051) — separate from the
 * staff-curated job_postings board.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_workspace_id
 * @property int $created_by_id
 * @property string $title
 * @property string $description
 * @property string|null $role_family
 * @property array<int, string>|null $skills
 * @property int $experience_min_years
 * @property int|null $experience_max_years
 * @property array<int, string>|null $locations
 * @property bool $remote
 * @property int|null $ctc_min_paise
 * @property int|null $ctc_max_paise
 * @property bool $ctc_visible
 * @property int $openings
 * @property array<int, array<string, mixed>>|null $knockout_questions
 * @property EmployerJobStatus $status
 * @property \Illuminate\Support\Carbon|null $published_at
 */
final class EmployerJob extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\EmployerJobFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'employer_workspace_id',
        'created_by_id',
        'title',
        'description',
        'role_family',
        'skills',
        'experience_min_years',
        'experience_max_years',
        'locations',
        'remote',
        'ctc_min_paise',
        'ctc_max_paise',
        'ctc_visible',
        'openings',
        'knockout_questions',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'locations' => 'array',
            'knockout_questions' => 'array',
            'remote' => 'boolean',
            'ctc_visible' => 'boolean',
            'status' => EmployerJobStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EmployerWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(EmployerWorkspace::class, 'employer_workspace_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return HasMany<JdMock, $this> */
    public function mocks(): HasMany
    {
        return $this->hasMany(JdMock::class);
    }

    /** Latest ready mock, else latest of any status. */
    public function currentMock(): ?JdMock
    {
        return $this->mocks()->where('status', JdMockStatus::Ready->value)->orderByDesc('version')->first()
            ?? $this->mocks()->orderByDesc('version')->first();
    }
}

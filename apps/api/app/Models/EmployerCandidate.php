<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A candidate from the client's own records — their candidate database or a
 * Naukri export they uploaded. Owned by one workspace; the shortlist agent
 * ranks these alongside BrowseJobs students.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_workspace_id
 * @property int|null $employer_candidate_import_id
 * @property string $source
 * @property string $dedupe_key
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $current_title
 * @property string|null $current_company
 * @property string|null $location
 * @property string|null $experience_years
 * @property string|null $notice_period
 * @property string|null $current_ctc
 * @property string|null $expected_ctc
 * @property string|null $education
 * @property list<string>|null $skills
 * @property string|null $summary
 */
final class EmployerCandidate extends Model
{
    use BelongsToTenant;

    public const SOURCE_CLIENT_DB = 'client_db';

    public const SOURCE_NAUKRI = 'naukri';

    public const SOURCES = [self::SOURCE_CLIENT_DB, self::SOURCE_NAUKRI];

    protected $fillable = [
        'tenant_id', 'employer_workspace_id', 'employer_candidate_import_id', 'source', 'dedupe_key',
        'name', 'email', 'phone', 'current_title', 'current_company', 'location', 'experience_years',
        'notice_period', 'current_ctc', 'expected_ctc', 'education', 'skills', 'summary',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'experience_years' => 'decimal:1',
        ];
    }

    /** @return BelongsTo<EmployerWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(EmployerWorkspace::class, 'employer_workspace_id');
    }
}

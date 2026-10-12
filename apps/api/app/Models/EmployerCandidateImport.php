<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One uploaded candidate sheet (client database or Naukri export) and what
 * came of it — so the client can see "62 added, 4 skipped: no phone or email".
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_workspace_id
 * @property string $source
 * @property string $original_name
 * @property int $rows_total
 * @property int $created_count
 * @property int $updated_count
 * @property int $skipped_count
 * @property list<string>|null $errors
 * @property int|null $imported_by_user_id
 */
final class EmployerCandidateImport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'employer_workspace_id', 'source', 'original_name', 'rows_total',
        'created_count', 'updated_count', 'skipped_count', 'errors', 'imported_by_user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'errors' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }
}

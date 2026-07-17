<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A consented offer celebration (PRD §6.18). `consented_at` is mandatory at
 * creation — there is no unconsented celebration. Anonymous mode keeps the
 * student linked (for gap guidance) but displays `anonymous_label`.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $student_id
 * @property string $display_mode
 * @property string|null $anonymous_label
 * @property string $role_title
 * @property string|null $company
 * @property Carbon $consented_at
 * @property Carbon|null $published_at
 * @property bool $is_active
 */
class Celebration extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'student_id', 'display_mode', 'anonymous_label', 'role_title',
        'company', 'consented_at', 'created_by', 'published_at', 'is_active',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** The name shown on the wall and in broadcasts. */
    public function displayName(): string
    {
        if ($this->display_mode === 'anonymous') {
            return $this->anonymous_label ?: 'A BrowseJobs student';
        }

        return $this->student->name;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'published_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}

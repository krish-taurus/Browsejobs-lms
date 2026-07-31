<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployerRole;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\EmployerWorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $website
 * @property string|null $industry
 * @property string|null $company_size
 * @property string|null $gstin
 * @property string|null $logo_path
 * @property array<int, string>|null $locations
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EmployerWorkspace extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EmployerWorkspaceFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    /** Access is cut for the whole team, enforced in ResolvesMembership. */
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'website',
        'industry',
        'company_size',
        'gstin',
        'logo_path',
        'locations',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'locations' => 'array',
        ];
    }

    /** @return HasMany<EmployerMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(EmployerMember::class);
    }

    /** @return HasMany<EmployerInvite, $this> */
    public function invites(): HasMany
    {
        return $this->hasMany(EmployerInvite::class);
    }

    /** @return HasMany<EmployerJob, $this> */
    public function jobs(): HasMany
    {
        return $this->hasMany(EmployerJob::class);
    }

    public function memberFor(User $user): ?EmployerMember
    {
        return $this->members()->where('user_id', $user->id)->first();
    }

    public function roleFor(User $user): ?EmployerRole
    {
        return $this->memberFor($user)?->role;
    }
}

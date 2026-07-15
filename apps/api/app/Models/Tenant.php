<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tenant (institute) on the platform. BrowseJobs is tenant 1 and the default
 * theme. Tenants are the root of the ownership tree, so this model does NOT use
 * {@see BelongsToTenant} — it is never itself scoped.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $domain
 * @property array<string, mixed>|null $branding
 * @property array<string, mixed>|null $feature_flags
 * @property string $plan
 * @property string $status
 * @property string $batch_numbering_pattern
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'branding',
        'feature_flags',
        'plan',
        'status',
        'batch_numbering_pattern',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'feature_flags' => 'array',
        ];
    }
}

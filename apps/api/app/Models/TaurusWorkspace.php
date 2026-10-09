<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One business's Taurus (ADR 0052): its own agents, members, bot token and
 * LLM / voice keys. Exactly one workspace per tenant is the founder's
 * "Taurus HQ" (`is_owner`), the only one that may borrow platform keys.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property bool $is_owner
 * @property string|null $brain_provider
 * @property string|null $brain_model
 * @property string|null $ingest_token_hash
 * @property string|null $ingest_token_last4
 * @property int|null $created_by
 * @property Carbon $created_at
 */
class TaurusWorkspace extends Model
{
    use BelongsToTenant;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'status',
        'is_owner',
        'brain_provider',
        'brain_model',
        'ingest_token_hash',
        'ingest_token_last4',
        'created_by',
    ];

    protected $hidden = ['ingest_token_hash'];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'is_owner' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** @return HasMany<TaurusWorkspaceMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(TaurusWorkspaceMember::class);
    }

    /** @return HasMany<TaurusWorkspaceCredential, $this> */
    public function credentials(): HasMany
    {
        return $this->hasMany(TaurusWorkspaceCredential::class);
    }

    /** @return HasMany<TaurusWorkspaceInvite, $this> */
    public function invites(): HasMany
    {
        return $this->hasMany(TaurusWorkspaceInvite::class);
    }

    /** @return HasMany<TaurusAgent, $this> */
    public function agents(): HasMany
    {
        return $this->hasMany(TaurusAgent::class);
    }

    /** @return HasMany<TaurusTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(TaurusTask::class);
    }

    /** @return HasMany<TaurusEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TaurusEvent::class);
    }

    public function memberFor(User $user): ?TaurusWorkspaceMember
    {
        return $this->members()->where('user_id', $user->id)->first();
    }

    public function credential(string $provider): ?TaurusWorkspaceCredential
    {
        return $this->credentials()->where('provider', $provider)->first();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaurusWorkspaceRole;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An invitation into a Taurus workspace (ADR 0052). Only the sha256 of the
 * token is stored; the raw token travels once, in the invite URL.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $taurus_workspace_id
 * @property string $email
 * @property string|null $name
 * @property TaurusWorkspaceRole $role
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property int|null $invited_by
 */
class TaurusWorkspaceInvite extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'taurus_workspace_id',
        'email',
        'name',
        'role',
        'token_hash',
        'expires_at',
        'accepted_at',
        'invited_by',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'role' => TaurusWorkspaceRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TaurusWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(TaurusWorkspace::class, 'taurus_workspace_id');
    }
}

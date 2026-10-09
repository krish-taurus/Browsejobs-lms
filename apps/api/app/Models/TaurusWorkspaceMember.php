<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaurusWorkspaceRole;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's membership of one Taurus workspace (ADR 0052).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $taurus_workspace_id
 * @property int $user_id
 * @property TaurusWorkspaceRole $role
 */
class TaurusWorkspaceMember extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'taurus_workspace_id', 'user_id', 'role'];

    protected function casts(): array
    {
        return [
            'role' => TaurusWorkspaceRole::class,
        ];
    }

    /** @return BelongsTo<TaurusWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(TaurusWorkspace::class, 'taurus_workspace_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

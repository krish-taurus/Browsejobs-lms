<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployerRole;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\EmployerInviteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Single-use, expiring workspace invitation (PRD-E F1).
 *
 * Token is consumed atomically in AcceptEmployerInvite — accepted_at is
 * checked and set inside one UPDATE guard so a token can never admit two
 * users (CLAUDE.md magic-link rules).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_workspace_id
 * @property int $invited_by_id
 * @property string $email
 * @property EmployerRole $role
 * @property string $token
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EmployerInvite extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EmployerInviteFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'employer_workspace_id',
        'invited_by_id',
        'email',
        'role',
        'token',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => EmployerRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EmployerWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(EmployerWorkspace::class, 'employer_workspace_id');
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}

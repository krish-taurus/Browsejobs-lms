<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployerRole;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_workspace_id
 * @property int $user_id
 * @property EmployerRole $role
 * @property \Illuminate\Support\Carbon $joined_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class EmployerMember extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\EmployerMemberFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'employer_workspace_id',
        'user_id',
        'role',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => EmployerRole::class,
            'joined_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EmployerWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(EmployerWorkspace::class, 'employer_workspace_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

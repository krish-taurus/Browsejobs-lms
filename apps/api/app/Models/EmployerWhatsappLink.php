<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A client's phone linked to their employer workspace. Messages from this
 * number go to the hiring chat agent; `draft` holds the job being prepared
 * while the agent waits for YES (`state` = confirming).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_workspace_id
 * @property int $user_id
 * @property string $phone
 * @property string|null $profile_name
 * @property string $state
 * @property array<string, mixed>|null $draft
 * @property Carbon|null $last_inbound_at
 */
final class EmployerWhatsappLink extends Model
{
    use BelongsToTenant;

    public const STATE_IDLE = 'idle';

    public const STATE_CONFIRMING = 'confirming';

    protected $fillable = [
        'tenant_id', 'employer_workspace_id', 'user_id', 'phone', 'profile_name', 'state', 'draft', 'last_inbound_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'draft' => 'array',
            'last_inbound_at' => 'datetime',
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

    /** @return HasMany<EmployerWhatsappMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(EmployerWhatsappMessage::class);
    }
}

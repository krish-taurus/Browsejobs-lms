<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The code behind a member's "post jobs on WhatsApp" QR. Sending
 * "JOIN <code>" to the business number links the sender's phone to this
 * workspace as this member. Reusable until it expires or is replaced.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_workspace_id
 * @property int $user_id
 * @property string $code
 * @property Carbon $expires_at
 */
final class EmployerWhatsappCode extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'employer_workspace_id', 'user_id', 'code', 'expires_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
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

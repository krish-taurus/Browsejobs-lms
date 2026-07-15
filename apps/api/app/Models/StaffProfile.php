<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Public-facing profile for a staff user — the "who's who" a student sees when
 * raising a concern (PRD §4). Support-team memberships live on the user.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property string|null $title
 * @property string|null $description
 */
class StaffProfile extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'user_id', 'title', 'description'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

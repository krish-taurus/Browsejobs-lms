<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A support team that handles a concern category (PRD §6.13). Staff join teams
 * to receive routed tickets; this is the membership source for that routing.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $name
 * @property string $slug
 * @property string $category
 * @property string|null $description
 */
class SupportTeam extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'name', 'slug', 'category', 'description'];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'support_team_user');
    }
}

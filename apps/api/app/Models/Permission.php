<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A granular capability, granted to roles. Platform-wide (not tenant-scoped).
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 */
class Permission extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['slug', 'name', 'description'];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}

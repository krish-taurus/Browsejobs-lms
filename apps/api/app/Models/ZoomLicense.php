<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Zoom host license in the pool, optionally allocated to a mentor (PRD §6.3).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $label
 * @property string $zoom_user_id
 * @property int|null $mentor_id
 * @property bool $active
 */
class ZoomLicense extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'label', 'zoom_user_id', 'mentor_id', 'active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }
}

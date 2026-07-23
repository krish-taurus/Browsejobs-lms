<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Zoom host license in the pool (PRD §6.3). Never dedicated to a person —
 * sessions claim whichever license is free at their time slot (ADR 0043).
 * The legacy `mentor_id` column remains in the schema but is no longer used.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $label
 * @property string $zoom_user_id
 * @property bool $active
 */
class ZoomLicense extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'label', 'zoom_user_id', 'active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}

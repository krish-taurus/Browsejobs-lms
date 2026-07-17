<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A recipient's cached "Your Path to the Same" card (PRD §6.18): three
 * concrete actions built from THEIR mastery gaps. One per (celebration, user)
 * so the AI cost is paid once.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $celebration_id
 * @property int $user_id
 * @property string $intro
 * @property list<string> $actions
 * @property string $source
 */
class CelebrationGuidance extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'celebration_id', 'user_id', 'intro', 'actions', 'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['actions' => 'array'];
    }
}

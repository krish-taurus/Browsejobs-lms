<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student's mock quota for one module (PRD §6.6). Opened on module completion,
 * advanced FIFO as mocks finish, and cleared once `completed` reaches `required`.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int $module_id
 * @property int $required
 * @property int $completed
 * @property Carbon $unlocked_at
 * @property Carbon|null $cleared_at
 */
final class ModuleMockRequirement extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'module_id', 'required', 'completed', 'unlocked_at', 'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'integer',
            'completed' => 'integer',
            'unlocked_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remaining(): int
    {
        return max(0, $this->required - $this->completed);
    }
}

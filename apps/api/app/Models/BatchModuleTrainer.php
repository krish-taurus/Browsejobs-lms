<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assigns a trainer to a specific module within a batch (PRD §6.3). Overrides the
 * batch's default (lead) trainer for that module's classes.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $batch_id
 * @property int $module_id
 * @property int $user_id
 */
final class BatchModuleTrainer extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'batch_id', 'module_id', 'user_id'];

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** @return BelongsTo<User, $this> */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

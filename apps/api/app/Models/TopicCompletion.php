<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Records that a student finished a topic. The write is what drives the
 * TopicCompleted / ModuleCompleted domain events.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int $topic_id
 * @property Carbon $completed_at
 */
class TopicCompletion extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'user_id', 'topic_id', 'completed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}

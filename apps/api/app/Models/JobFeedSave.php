<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A student's save/dismiss state on a feed item (PRD §6.22).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int $job_feed_item_id
 * @property string $state
 */
class JobFeedSave extends Model
{
    use BelongsToTenant;

    public const STATE_SAVED = 'saved';

    public const STATE_DISMISSED = 'dismissed';

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'user_id', 'job_feed_item_id', 'state'];
}

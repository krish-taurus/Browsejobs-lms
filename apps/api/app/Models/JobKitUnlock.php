<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One user's Interview Kit unlock for one job posting (ADR 0048).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int $job_feed_item_id
 */
class JobKitUnlock extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'user_id', 'job_feed_item_id'];
}

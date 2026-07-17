<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One recurring weekly availability window, minutes-of-day in IST
 * (weekday 0 = Sunday). Slots are cut from these by the SlotFinder.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $mentor_profile_id
 * @property int $weekday
 * @property int $start_minute
 * @property int $end_minute
 */
class MentorAvailability extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'mentor_profile_id', 'weekday', 'start_minute', 'end_minute'];
}

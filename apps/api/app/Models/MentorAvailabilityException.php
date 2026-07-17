<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A dated override to the weekly pattern: a whole day off (null window) or
 * a blocked window within the day (IST minutes).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $mentor_profile_id
 * @property Carbon $date
 * @property int|null $start_minute
 * @property int|null $end_minute
 */
class MentorAvailabilityException extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'mentor_profile_id', 'date', 'start_minute', 'end_minute'];

    public function isFullDay(): bool
    {
        return $this->start_minute === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}

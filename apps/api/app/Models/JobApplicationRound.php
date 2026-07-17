<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One real interview round + its student debrief (PRD §6.11). The debrief
 * text is piped into the P4.2 transcript pipeline so every real round
 * enriches the interview bank.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $job_application_id
 * @property int $round_no
 * @property string|null $round_type
 * @property Carbon $happened_on
 * @property string $outcome
 * @property string|null $debrief
 * @property int|null $interview_transcript_id
 */
class JobApplicationRound extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'job_application_id', 'round_no', 'round_type',
        'happened_on', 'outcome', 'debrief', 'interview_transcript_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['happened_on' => 'date'];
    }
}

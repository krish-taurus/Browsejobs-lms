<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The message agent's thread with one candidate about one interview round:
 * the dates offered on WhatsApp, the one they picked, and the private link
 * (/hire-interview/{token}) that opens the voice interview on that day.
 *
 * Round "screen" is the AI screening interview the shortlist agent sends to
 * someone whose CV matches but who has no AI interview for the role yet: it
 * is open from the start (no day to pick) and runs the JD's own interview.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_job_id
 * @property int $employer_job_application_id
 * @property int $candidate_user_id
 * @property int|null $employer_interview_id
 * @property string $round
 * @property string $phone
 * @property string $status
 * @property list<string>|null $date_options
 * @property Carbon|null $chosen_date
 * @property Carbon|null $scheduled_at the booked slot (day + time)
 * @property string|null $zoom_meeting_id
 * @property string|null $zoom_join_url
 * @property string|null $bot_code private key in the interviewer page the meeting bot opens
 * @property string|null $bot_id
 * @property string|null $bot_status
 * @property string $token
 * @property string|null $wamid
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon|null $replied_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $reminded_at reminder sent about an hour before the slot
 * @property Carbon|null $nudged_at nudge sent when not started half an hour after it
 * @property Carbon|null $chased_at asked again, with fresh slots, after a day without a reply
 */
final class InterviewInvite extends Model
{
    use BelongsToTenant;

    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const SCHEDULED = 'scheduled';

    public const OPEN = 'open';

    public const DONE = 'done';

    public const DECLINED = 'declined';

    public const FAILED = 'failed';

    public const ROUND_SCREEN = 'screen';

    /** Statuses in which a WhatsApp reply from the candidate belongs to this thread. */
    public const LISTENING = [self::SENT, self::SCHEDULED, self::OPEN];

    protected $fillable = [
        'tenant_id', 'employer_job_id', 'employer_job_application_id', 'candidate_user_id', 'employer_interview_id',
        'round', 'phone', 'status', 'date_options', 'chosen_date', 'scheduled_at', 'token', 'wamid', 'error',
        'zoom_meeting_id', 'zoom_join_url', 'bot_code', 'bot_id', 'bot_status',
        'sent_at', 'replied_at', 'opened_at', 'reminded_at', 'nudged_at', 'chased_at', 'sent_by_user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date_options' => 'array',
            'chosen_date' => 'date',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'replied_at' => 'datetime',
            'opened_at' => 'datetime',
            'reminded_at' => 'datetime',
            'nudged_at' => 'datetime',
            'chased_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EmployerJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(EmployerJob::class, 'employer_job_id');
    }

    /** @return BelongsTo<EmployerJobApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(EmployerJobApplication::class, 'employer_job_application_id');
    }

    /** @return BelongsTo<User, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id');
    }

    /** @return BelongsTo<EmployerInterview, $this> */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(EmployerInterview::class, 'employer_interview_id');
    }

    public function link(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/hire-interview/'.$this->token;
    }

    /** How the round is named to the candidate: "AI screening", "L1", "L2". */
    public function roundLabel(): string
    {
        return $this->round === self::ROUND_SCREEN ? 'AI screening' : strtoupper($this->round);
    }

    /** "Tue 14 Oct, 11:00 AM" in India time, or just the day when no time is set. */
    public function slotLabel(): ?string
    {
        if ($this->scheduled_at !== null) {
            return $this->scheduled_at->copy()->setTimezone('Asia/Kolkata')->format('D j M, g:i A');
        }

        return $this->chosen_date?->format('D j M');
    }

    public function onZoom(): bool
    {
        return $this->zoom_join_url !== null && $this->zoom_join_url !== '';
    }

    public function firstName(): string
    {
        $first = trim(explode(' ', trim((string) $this->candidate?->name))[0] ?? '');

        return $first !== '' ? mb_substr($first, 0, 30) : 'there';
    }
}

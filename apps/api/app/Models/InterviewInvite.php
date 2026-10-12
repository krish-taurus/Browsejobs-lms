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
 * @property string $token
 * @property string|null $wamid
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon|null $replied_at
 * @property Carbon|null $opened_at
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

    /** Statuses in which a WhatsApp reply from the candidate belongs to this thread. */
    public const LISTENING = [self::SENT, self::SCHEDULED, self::OPEN];

    protected $fillable = [
        'tenant_id', 'employer_job_id', 'employer_job_application_id', 'candidate_user_id', 'employer_interview_id',
        'round', 'phone', 'status', 'date_options', 'chosen_date', 'token', 'wamid', 'error',
        'sent_at', 'replied_at', 'opened_at', 'sent_by_user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date_options' => 'array',
            'chosen_date' => 'date',
            'sent_at' => 'datetime',
            'replied_at' => 'datetime',
            'opened_at' => 'datetime',
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

    public function firstName(): string
    {
        $first = trim(explode(' ', trim((string) $this->candidate?->name))[0] ?? '');

        return $first !== '' ? mb_substr($first, 0, 30) : 'there';
    }
}

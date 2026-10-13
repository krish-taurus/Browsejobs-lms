<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The PDF report for one graded interview round (and the candidate's CV),
 * and whether it has reached the client's WhatsApp.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_interview_id
 * @property int $employer_job_id
 * @property string $pdf_path
 * @property string|null $cv_path
 * @property int $flags
 * @property int $possible_flags
 * @property string $caption
 * @property string $status
 * @property int|null $employer_whatsapp_link_id
 * @property Carbon|null $sent_at
 */
final class EmployerInterviewReport extends Model
{
    use BelongsToTenant;

    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    /** Nobody at the company has linked WhatsApp; the report is on the candidate page only. */
    public const NO_CHAT = 'no_chat';

    protected $fillable = [
        'tenant_id', 'employer_interview_id', 'employer_job_id', 'pdf_path', 'cv_path', 'flags', 'possible_flags',
        'caption', 'status', 'employer_whatsapp_link_id', 'sent_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'flags' => 'integer', 'possible_flags' => 'integer'];
    }

    /** @return BelongsTo<EmployerInterview, $this> */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(EmployerInterview::class, 'employer_interview_id');
    }

    /** @return BelongsTo<EmployerJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(EmployerJob::class, 'employer_job_id');
    }
}

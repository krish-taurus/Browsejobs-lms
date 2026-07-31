<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One ingested real-interview transcript (PRD §6.6). `raw_text` is the
 * anonymised working copy — the parser never sees the original, which lives
 * encrypted at `encrypted_path` with restricted access.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $uploader_id
 * @property int|null $course_id
 * @property string $source
 * @property string|null $original_name
 * @property string|null $encrypted_path
 * @property string|null $raw_text
 * @property string|null $role_title
 * @property string|null $outcome
 * @property string $status
 * @property string|null $parse_error
 * @property int $questions_found
 * @property Carbon $consent_confirmed_at
 */
class InterviewTranscript extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_TRANSCRIBING = 'transcribing';

    public const STATUS_PARSING = 'parsing';

    public const STATUS_PARSED = 'parsed';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'uploader_id', 'course_id', 'source', 'original_name',
        'encrypted_path', 'raw_text', 'role_title', 'outcome', 'status',
        'parse_error', 'questions_found', 'consent_confirmed_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<RealInterviewQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(RealInterviewQuestion::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['consent_confirmed_at' => 'datetime'];
    }
}

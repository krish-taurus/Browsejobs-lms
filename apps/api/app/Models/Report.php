<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An AI-narrated report or digest (PRD §6.10). Recipient is the student, trainer, or
 * counselor it was generated for.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $recipient_id
 * @property ReportType $type
 * @property string $title
 * @property string $narrative
 * @property array<string, mixed>|null $data
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $ai_event_id
 * @property Carbon|null $read_at
 */
class Report extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'recipient_id', 'type', 'title', 'narrative', 'data',
        'period_start', 'period_end', 'subject_type', 'subject_id', 'ai_event_id', 'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'data' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
            'read_at' => 'datetime',
        ];
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

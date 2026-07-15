<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordingStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stored recording for a live session (pulled from Zoom into S3/MinIO).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $live_session_id
 * @property int|null $topic_id
 * @property string $title
 * @property string|null $storage_path
 * @property int|null $size_bytes
 * @property int|null $duration_seconds
 * @property RecordingStatus $status
 */
class Recording extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'live_session_id', 'topic_id', 'title',
        'storage_path', 'size_bytes', 'duration_seconds', 'status',
    ];

    /**
     * @return BelongsTo<LiveSession, $this>
     */
    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RecordingStatus::class,
        ];
    }
}

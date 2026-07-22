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
 * @property string|null $play_url
 * @property string|null $passcode
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
        'storage_path', 'play_url', 'passcode', 'size_bytes', 'duration_seconds', 'status',
    ];

    /**
     * Ready to watch: a Zoom Cloud recording has a play URL; a legacy imported one
     * has a storage path. Either way the row is watchable.
     */
    public function isWatchable(): bool
    {
        return $this->play_url !== null || $this->storage_path !== null;
    }

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

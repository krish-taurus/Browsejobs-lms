<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VideoSource;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * The video attached to a `video` lesson: an external embed URL, a Zoom
 * recording from the library, or (later) an uploaded file on the Space.
 * One per lesson.
 *
 * @property VideoSource $source
 */
final class LessonVideo extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'lesson_id', 'source', 'title', 'url', 'recording_id', 'path'];

    protected function casts(): array
    {
        return ['source' => VideoSource::class];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }

    /** A URL the browser can play: external URL as-is, recording/upload as a signed link. */
    public function playableUrl(): ?string
    {
        return match ($this->source) {
            VideoSource::Url => $this->url,
            VideoSource::Recording => $this->recording?->play_url // Zoom Cloud recording
                ?? ($this->recording?->storage_path !== null
                    ? Storage::disk('s3')->temporaryUrl($this->recording->storage_path, now()->addHours(3))
                    : null),
            VideoSource::Upload => $this->path !== null
                ? Storage::disk('s3')->temporaryUrl($this->path, now()->addHours(3))
                : null,
        };
    }

    /** True when the source is an external embed (iframe) vs a direct file (video tag). */
    public function isEmbed(): bool
    {
        return $this->source === VideoSource::Url;
    }
}

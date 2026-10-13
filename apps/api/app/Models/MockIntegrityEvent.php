<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something the interview room noticed, with a still of the candidate when
 * the camera was on. `flag` is a clear signal (left the tab, pasted, face
 * gone); `possible` is a camera heuristic with known false positives (a
 * second face, eyes off the screen) for a human to judge from the still;
 * `info` is a random check-in photo.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $mock_interview_id
 * @property string $kind
 * @property string $severity
 * @property int|null $elapsed_seconds
 * @property string|null $image_path
 * @property Carbon $occurred_at
 */
final class MockIntegrityEvent extends Model
{
    use BelongsToTenant;

    public const FLAG = 'flag';

    public const POSSIBLE = 'possible';

    public const INFO = 'info';

    /** What the room can report, and how seriously each is read. */
    public const KINDS = [
        'tab_hidden' => self::FLAG,
        'window_blur' => self::FLAG,
        'paste' => self::FLAG,
        'face_missing' => self::FLAG,
        'multi_face' => self::POSSIBLE,
        'gaze_down' => self::POSSIBLE,
        'gaze_side' => self::POSSIBLE,
        'check_in' => self::INFO,
    ];

    public const LABELS = [
        'tab_hidden' => 'Left the interview tab',
        'window_blur' => 'Interview window lost focus',
        'paste' => 'Tried to paste an answer',
        'face_missing' => 'Face not visible on camera',
        'multi_face' => 'Possibly a second person in frame',
        'gaze_down' => 'Possibly looking down (phone?)',
        'gaze_side' => 'Possibly looking to the side (notes or a second screen?)',
        'check_in' => 'Check-in photo',
    ];

    /** Most events a single interview may record, and most stills it may store. */
    public const MAX_EVENTS = 120;

    public const MAX_IMAGES = 24;

    protected $fillable = ['tenant_id', 'mock_interview_id', 'kind', 'severity', 'elapsed_seconds', 'image_path', 'occurred_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'elapsed_seconds' => 'integer',
        ];
    }

    /** @return BelongsTo<MockInterview, $this> */
    public function mock(): BelongsTo
    {
        return $this->belongsTo(MockInterview::class, 'mock_interview_id');
    }

    public function label(): string
    {
        return self::LABELS[$this->kind] ?? $this->kind;
    }
}

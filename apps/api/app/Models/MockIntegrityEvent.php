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
        // Lip-sync: does the visible mouth match the sound of the answer? (Browser estimates.)
        'lip_voice_mismatch' => self::POSSIBLE,
        'lip_lag' => self::POSSIBLE,
        'lip_no_voice' => self::POSSIBLE,
        // Server side, from the recording: more than one voice answering.
        'second_voice' => self::POSSIBLE,
        // Measurements, not moments: one per interview, shown as a summary.
        'lip_sync_summary' => self::INFO,
        'voice_check' => self::INFO,
    ];

    /** Kinds that summarise the whole interview instead of marking a moment. */
    public const MEASUREMENTS = ['lip_sync_summary', 'voice_check'];

    /** The numbers each kind may carry, and nothing else. */
    public const DETAIL_KEYS = [
        'lip_voice_mismatch' => ['secs', 'pct'],
        'lip_lag' => ['lag_ms', 'pct', 'n'],
        'lip_no_voice' => ['secs'],
        'second_voice' => ['secs', 'voices'],
        'lip_sync_summary' => ['speech_s', 'match_pct', 'lag_ms', 'onsets', 'face_pct', 'flags'],
        'voice_check' => ['voices', 'secs_a', 'secs_b'],
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
        'lip_voice_mismatch' => 'Speech without matching lip movement',
        'lip_lag' => 'Lips followed the voice (possible lip-sync to another person)',
        'lip_no_voice' => 'Lips moving with no sound',
        'second_voice' => 'A second voice answered',
        'lip_sync_summary' => 'Lip-sync check',
        'voice_check' => 'Voice check',
    ];

    /** Most events a single interview may record, and most stills it may store. */
    public const MAX_EVENTS = 120;

    public const MAX_IMAGES = 24;

    protected $fillable = ['tenant_id', 'mock_interview_id', 'kind', 'severity', 'elapsed_seconds', 'image_path', 'occurred_at', 'detail'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'elapsed_seconds' => 'integer',
            'detail' => 'array',
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

    public function isMeasurement(): bool
    {
        return in_array($this->kind, self::MEASUREMENTS, true);
    }

    /** The finding in plain words, from the numbers. Null when there is nothing to add. */
    public function detailText(): ?string
    {
        $d = (array) ($this->detail ?? []);
        $n = fn (string $k): ?int => isset($d[$k]) && is_numeric($d[$k]) ? (int) $d[$k] : null;

        return match ($this->kind) {
            'lip_voice_mismatch' => $n('secs') !== null
                ? "Speech for about {$n('secs')} s while the mouth barely moved".($n('pct') !== null ? " (lips moving for {$n('pct')}% of it)." : '.')
                : null,
            'lip_lag' => $n('lag_ms') !== null
                ? "The lips started about {$n('lag_ms')} ms after the sound on {$n('pct')}% of {$n('n')} phrases. Someone speaking for themselves is usually within about 250 ms."
                : null,
            'lip_no_voice' => $n('secs') !== null ? "Lips moved for about {$n('secs')} s with no sound at all." : null,
            'second_voice' => $n('secs') !== null ? "A second voice, different from the first, spoke for about {$n('secs')} s of the answers." : null,
            default => null,
        };
    }
}

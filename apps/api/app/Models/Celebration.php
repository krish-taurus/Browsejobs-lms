<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A consented offer celebration (PRD §6.18). `consented_at` is mandatory at
 * creation — there is no unconsented celebration. Anonymous mode keeps the
 * student linked (for gap guidance) but displays `anonymous_label`.
 *
 * student_id is nullable: a real person's story can be published without an
 * LMS account behind it (an older alum, someone from before this system
 * existed) — student_name carries the name in that case. When student_id
 * IS set, the live account name always wins over student_name.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $student_id
 * @property string|null $student_name
 * @property string $display_mode
 * @property string|null $anonymous_label
 * @property string $role_title
 * @property string|null $company
 * @property string|null $photo_path
 * @property string|null $video_url
 * @property Carbon $consented_at
 * @property Carbon|null $published_at
 * @property bool $is_active
 */
class Celebration extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'student_id', 'student_name', 'display_mode', 'anonymous_label', 'role_title',
        'company', 'photo_path', 'video_url', 'consented_at', 'created_by', 'published_at', 'is_active',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** The name shown on the wall and in broadcasts. */
    public function displayName(): string
    {
        if ($this->display_mode === 'anonymous') {
            return $this->anonymous_label ?: 'A BrowseJobs student';
        }

        return $this->student?->name ?? $this->student_name ?? 'A BrowseJobs student';
    }

    /** A short-lived signed URL for the uploaded photo, or null. */
    public function photoUrl(): ?string
    {
        if ($this->photo_path === null) {
            return null;
        }

        try {
            return Storage::disk('s3')->temporaryUrl($this->photo_path, now()->addHour());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'published_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}

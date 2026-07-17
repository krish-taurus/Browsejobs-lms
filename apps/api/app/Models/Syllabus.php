<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Content\ApproveSyllabus;
use App\Enums\SyllabusStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An AI-generated, trainer-approved course syllabus (PRD §6.2). One per course.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $course_id
 * @property string $title
 * @property string|null $content
 * @property SyllabusStatus $status
 * @property string|null $curriculum_hash
 * @property bool $is_stale
 * @property string $render_status
 * @property string|null $storage_path
 * @property int $version
 * @property int|null $generated_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
class Syllabus extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** Laravel would pluralize the model to `syllabi`; the table is `syllabuses`. */
    protected $table = 'syllabuses';

    public const RENDER_PENDING = 'pending';

    public const RENDER_RENDERED = 'rendered';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'course_id', 'title', 'content', 'status', 'curriculum_hash',
        'is_stale', 'render_status', 'storage_path', 'version',
        'generated_by', 'approved_by', 'approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SyllabusStatus::class,
            'is_stale' => 'boolean',
            'version' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === SyllabusStatus::Approved;
    }

    public function isRendered(): bool
    {
        return $this->render_status === self::RENDER_RENDERED && $this->storage_path !== null;
    }

    /**
     * Serveable to a student or lead. Keyed off the rendered artifact, not the
     * current status: only {@see ApproveSyllabus} renders, so a
     * rendered artifact is always a previously-approved one. This keeps the last
     * approved syllabus live while an edit/curriculum-change regenerates a new draft
     * (PRD §6.2/§6.21 — running batches unaffected). A never-approved syllabus has
     * render_status=pending and is not downloadable.
     */
    public function isDownloadable(): bool
    {
        return $this->isRendered();
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An AI Tutor knowledge-base source document (PRD §6.4). Ingested from existing
 * content or trainer-authored; chunked into `knowledge_chunks` for retrieval.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $title
 * @property string $source_type
 * @property int|null $source_id
 * @property string|null $category
 * @property string $body
 * @property int|null $course_id
 * @property int|null $lesson_id
 * @property bool $is_active
 * @property int|null $authored_by
 */
class KnowledgeDocument extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'title', 'source_type', 'source_id', 'category', 'body',
        'course_id', 'lesson_id', 'is_active', 'authored_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<KnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'document_id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}

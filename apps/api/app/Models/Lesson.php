<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LessonType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property int $topic_id
 * @property string $title
 * @property LessonType $type
 */
class Lesson extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'topic_id', 'title', 'type', 'position'];

    /**
     * @return BelongsTo<Topic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => LessonType::class];
    }
}

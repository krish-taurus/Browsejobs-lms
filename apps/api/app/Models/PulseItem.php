<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A curated Market Pulse source item (PRD §6.19) — staff-added market news
 * with a source URL, optionally tied to a course for the relevance line.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $title
 * @property string $url
 * @property string $source_name
 * @property string|null $course_slug
 * @property string|null $note
 * @property Carbon $published_on
 * @property bool $is_active
 */
class PulseItem extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'title', 'url', 'source_name', 'course_slug', 'note',
        'published_on', 'added_by', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}

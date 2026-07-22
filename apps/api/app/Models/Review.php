<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A student review shown on the public /reviews wall. Sources: google,
 * whatsapp, platform (brochure/testimonial). Imported, never fabricated.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $author_name
 * @property string|null $course_slug
 * @property int $rating
 * @property string $body
 * @property string $source
 * @property Carbon|null $reviewed_on
 * @property bool $is_active
 */
class Review extends Model
{
    use BelongsToTenant;

    public const SOURCES = ['google', 'justdial', 'whatsapp', 'platform'];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'author_name', 'author_meta', 'course_slug', 'rating', 'body',
        'source', 'reviewed_on', 'is_active', 'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'reviewed_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}

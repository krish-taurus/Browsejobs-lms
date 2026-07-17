<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A Content Hub release (PRD §6.19): podcast episode, YouTube video, or
 * Instagram post. Manual entries today; RSS/Graph ingestion adapters plug in
 * behind the same table when channel credentials arrive.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $kind
 * @property string $title
 * @property string $url
 * @property string $source
 * @property Carbon $published_at
 * @property bool $is_active
 */
class ContentHubItem extends Model
{
    use BelongsToTenant;

    public const KINDS = ['youtube', 'instagram', 'podcast'];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'kind', 'title', 'url', 'source', 'published_at', 'created_by', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}

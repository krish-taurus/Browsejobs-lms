<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A rotating promotional banner on the student dashboard (candidate
 * request, Aug 2026) — managed entirely from the CRM. Deliberately not
 * scoped with BelongsToTenant: like CareerBoostPackage/Product, this is a
 * platform-wide marketing catalogue an admin curates, not per-request
 * tenant data.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $title
 * @property string|null $subtitle
 * @property string $image_path
 * @property string|null $link_url
 * @property string|null $link_label
 * @property int $sort_order
 * @property bool $enabled
 * @property string|null $youtube_video_id
 */
class DashboardBanner extends Model
{
    protected $fillable = [
        'tenant_id', 'title', 'subtitle', 'image_path', 'link_url', 'link_label', 'sort_order', 'enabled', 'youtube_video_id',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    /**
     * The banner's image. A manually uploaded banner stores a Spaces object
     * key, resolved to a public URL at read time. A banner imported from a
     * synced YouTube video (see youtube_video_id) stores that video's own
     * i.ytimg.com thumbnail URL directly instead — hotlinked, not
     * re-uploaded, since YouTube's own CDN already serves it reliably.
     */
    public function imageUrl(): string
    {
        if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
            return $this->image_path;
        }

        return Storage::disk('s3')->url($this->image_path);
    }
}

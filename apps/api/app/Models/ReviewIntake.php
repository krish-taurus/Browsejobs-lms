<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A Drive-synced review image awaiting triage.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $drive_file_id
 * @property string $file_name
 * @property string $image_path
 * @property string $status
 * @property string|null $published_kind
 * @property int|null $published_id
 */
final class ReviewIntake extends Model
{
    use BelongsToTenant;

    protected $table = 'review_intake';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_DISMISSED = 'dismissed';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'drive_file_id', 'file_name', 'image_path', 'status', 'published_kind', 'published_id',
    ];

    /** Short-lived signed URL for the synced image, or null. */
    public function imageUrl(): ?string
    {
        try {
            return Storage::disk('s3')->temporaryUrl($this->image_path, now()->addHour());
        } catch (Throwable) {
            return null;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One DPDP data-subject request (PRD §8): a student's access or deletion request.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property string $type
 * @property string $status
 * @property string|null $reason
 * @property array<string, mixed>|null $export
 * @property int|null $processed_by
 * @property Carbon|null $processed_at
 */
class DataRequest extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPE_ACCESS = 'access';

    public const TYPE_DELETION = 'deletion';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'status', 'reason', 'export', 'processed_by', 'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['export' => 'array', 'processed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

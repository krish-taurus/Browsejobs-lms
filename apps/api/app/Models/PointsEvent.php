<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PointsSource;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One earned points entry (append-only). The unique (user, source, source_key)
 * constraint is the idempotency guarantee: replayed webhooks or repeated
 * submits can never double-mint.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int|null $batch_id
 * @property PointsSource $source
 * @property string $source_key
 * @property int $points
 * @property Carbon $awarded_on
 */
class PointsEvent extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'batch_id', 'source', 'source_key', 'points', 'awarded_on',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => PointsSource::class,
            'points' => 'integer',
            'awarded_on' => 'date',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single dunning reminder sent for an instalment rung (PRD §6.8). The unique
 * (instalment_id, rung) index dedupes the daily ladder run.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $instalment_id
 * @property string $rung
 * @property string $channel
 * @property Carbon $sent_at
 */
class FeeReminder extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'instalment_id', 'rung', 'channel', 'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Instalment, $this>
     */
    public function instalment(): BelongsTo
    {
        return $this->belongsTo(Instalment::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One NPS pulse at a journey milestone (PRD §6.20). score null = pending.
 * Routing happens at submit: promoters → an unconditioned Google-review
 * invitation, detractors → instant counselor rescue.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property string $milestone
 * @property int|null $score
 * @property string|null $comment
 * @property string|null $routed
 * @property Carbon|null $responded_at
 */
class NpsPulse extends Model
{
    use BelongsToTenant;

    public const MILESTONES = ['week1', 'mid', 'pre_placement'];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'milestone', 'score', 'comment', 'routed', 'responded_at',
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
        return ['score' => 'integer', 'responded_at' => 'datetime'];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in an application's timeline (PRD-E F3/F5). actor_type is
 * user | rule | system so automated and manual moves are always
 * distinguishable in the employer UI.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employer_job_application_id
 * @property string|null $from_stage
 * @property string $to_stage
 * @property string $actor_type
 * @property int|null $actor_id
 * @property string|null $note
 * @property Carbon $occurred_at
 */
final class ApplicationStageTransition extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employer_job_application_id',
        'from_stage',
        'to_stage',
        'actor_type',
        'actor_id',
        'note',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EmployerJobApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(EmployerJobApplication::class, 'employer_job_application_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BatchMemberStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property int $batch_id
 * @property int $user_id
 * @property BatchMemberStatus $status
 * @property Carbon|null $enrolled_at
 */
class BatchMember extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'batch_id', 'user_id', 'status', 'enrolled_at'];

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

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
            'status' => BatchMemberStatus::class,
            'enrolled_at' => 'datetime',
        ];
    }
}

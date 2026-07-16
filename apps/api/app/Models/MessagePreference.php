<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageChannel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A student's channel preferences (PRD §6.9).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property MessageChannel $preferred_channel
 * @property bool $marketing_opt_in
 */
class MessagePreference extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'preferred_channel', 'marketing_opt_in',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_channel' => MessageChannel::class,
            'marketing_opt_in' => 'boolean',
        ];
    }
}

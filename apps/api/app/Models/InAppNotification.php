<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An in-app dashboard notification (PRD §6.9). Table `in_app_notifications` to
 * avoid colliding with Laravel's Notifiable `notifications` table.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property string $title
 * @property string|null $body
 * @property string|null $url
 * @property Carbon|null $read_at
 */
class InAppNotification extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'in_app_notifications';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'title', 'body', 'url', 'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}

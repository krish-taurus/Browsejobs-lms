<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Inbound provider webhook audit log (PRD §8 `webhooks_log`).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $provider
 * @property string|null $event
 * @property string|null $event_id
 * @property bool $signature_valid
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $processed_at
 */
class WebhookLog extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'webhooks_log';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'provider', 'event', 'event_id', 'signature_valid', 'payload', 'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One workspace's own key for an LLM provider or ElevenLabs (ADR 0052).
 * Encrypted at rest and never serialized; only `key_last4` is ever shown.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $taurus_workspace_id
 * @property string $provider
 * @property string $api_key
 * @property string|null $key_last4
 * @property string|null $model
 * @property string|null $base_url
 * @property string|null $voice_id
 */
class TaurusWorkspaceCredential extends Model
{
    use BelongsToTenant;

    public const ELEVENLABS = 'elevenlabs';

    protected $fillable = [
        'tenant_id',
        'taurus_workspace_id',
        'provider',
        'api_key',
        'key_last4',
        'model',
        'base_url',
        'voice_id',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
        ];
    }

    public function hasKey(): bool
    {
        return trim((string) $this->api_key) !== '';
    }

    /** @return BelongsTo<TaurusWorkspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(TaurusWorkspace::class, 'taurus_workspace_id');
    }
}

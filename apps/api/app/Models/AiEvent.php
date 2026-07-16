<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiEventStatus;
use App\Enums\AiPurpose;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One AI gateway call's cost record (CLAUDE.md). Money in integer micro-rupees.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $user_id
 * @property AiPurpose $purpose
 * @property string $model
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property int $cost_micros
 * @property int $latency_ms
 * @property AiEventStatus $status
 */
class AiEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'purpose', 'model', 'prompt_tokens', 'completion_tokens',
        'total_tokens', 'cost_micros', 'latency_ms', 'status', 'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => AiPurpose::class,
            'status' => AiEventStatus::class,
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'cost_micros' => 'integer',
            'latency_ms' => 'integer',
            'meta' => 'array',
        ];
    }
}

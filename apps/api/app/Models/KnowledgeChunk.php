<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A retrievable chunk of a knowledge document (PRD §6.4). Ranked lexically in PHP;
 * `embedding` is reserved for a future semantic phase.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $document_id
 * @property int $ordinal
 * @property string $content
 * @property int $token_estimate
 * @property int $term_count
 * @property array<int, float>|null $embedding
 */
class KnowledgeChunk extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'document_id', 'ordinal', 'content', 'token_estimate', 'term_count', 'embedding',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordinal' => 'integer',
            'token_estimate' => 'integer',
            'term_count' => 'integer',
            'embedding' => 'array',
        ];
    }

    /**
     * @return BelongsTo<KnowledgeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }
}

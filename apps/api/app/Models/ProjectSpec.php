<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ArchitectureSource;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The capstone spec attached to a `project` lesson (one per lesson). Holds the
 * tech stack, a details brief, and a structured architecture that is either
 * uploaded or AI-generated, rendered to a downloadable file on the Space.
 *
 * @property ArchitectureSource $architecture_source
 * @property list<string>|null $tech_stack
 * @property array{overview?: string, layers?: array<int, array{name: string, components: array<int, array{name: string, role?: string}>}>, flows?: array<int, array{from: string, to: string, label?: string}>}|null $architecture
 */
final class ProjectSpec extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'lesson_id', 'title', 'summary', 'tech_stack',
        'architecture', 'architecture_source', 'architecture_path',
    ];

    protected function casts(): array
    {
        return [
            'tech_stack' => 'array',
            'architecture' => 'array',
            'architecture_source' => ArchitectureSource::class,
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** True once a downloadable architecture file exists. */
    public function hasDownload(): bool
    {
        return $this->architecture_path !== null;
    }

    /** True when the AI structure is present (drives the live diagram + PDF). */
    public function hasStructure(): bool
    {
        return is_array($this->architecture) && ($this->architecture['layers'] ?? []) !== [];
    }
}

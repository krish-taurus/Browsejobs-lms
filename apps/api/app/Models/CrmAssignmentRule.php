<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-tenant counselor assignment + speed-to-lead SLA configuration (PRD §6.12).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $mode
 * @property array<string, int>|null $by_course_map
 * @property int|null $round_robin_pointer
 * @property int $sla_minutes
 */
class CrmAssignmentRule extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const MODE_ROUND_ROBIN = 'round_robin';

    public const MODE_BY_COURSE = 'by_course';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'mode', 'by_course_map', 'round_robin_pointer', 'sla_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'by_course_map' => 'array',
            'sla_minutes' => 'integer',
        ];
    }
}

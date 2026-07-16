<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CodeLanguage;
use App\Enums\SubmissionKind;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A student's code run/submission (PRD §6.4/§6.5) — the code-telemetry source.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int|null $lesson_id
 * @property CodeLanguage $language
 * @property string $source
 * @property string|null $stdout
 * @property string|null $stderr
 * @property string|null $status
 * @property int $passed_tests
 * @property int $total_tests
 * @property int $run_time_ms
 * @property int $memory_kb
 * @property SubmissionKind $kind
 */
class CodeSubmission extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'lesson_id', 'language', 'source', 'stdin', 'stdout', 'stderr',
        'status', 'passed_tests', 'total_tests', 'run_time_ms', 'memory_kb', 'kind',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'language' => CodeLanguage::class,
            'kind' => SubmissionKind::class,
            'passed_tests' => 'integer',
            'total_tests' => 'integer',
            'run_time_ms' => 'integer',
            'memory_kb' => 'integer',
        ];
    }
}

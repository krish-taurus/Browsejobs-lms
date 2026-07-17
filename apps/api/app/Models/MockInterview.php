<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One AI mock-interview session (PRD §6.6). Text mode in P4.1; the voice
 * transport (P4.3) reuses this record with mode='voice'.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property int $mock_blueprint_id
 * @property string $mode
 * @property string $status
 * @property int|null $overall_score
 * @property array<string, mixed>|null $scorecard
 * @property string|null $scorecard_source
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 */
class MockInterview extends Model
{
    use BelongsToTenant;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'user_id', 'mock_blueprint_id', 'mode', 'status',
        'overall_score', 'scorecard', 'scorecard_source', 'started_at', 'completed_at',
    ];

    /**
     * @return BelongsTo<MockBlueprint, $this>
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(MockBlueprint::class, 'mock_blueprint_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<MockTurn, $this>
     */
    public function turns(): HasMany
    {
        return $this->hasMany(MockTurn::class);
    }

    public function candidateAnswers(): int
    {
        return $this->turns()->where('role', MockTurn::ROLE_CANDIDATE)->count();
    }

    public function interviewerQuestions(): int
    {
        return $this->turns()->where('role', MockTurn::ROLE_INTERVIEWER)->count();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scorecard' => 'array',
            'overall_score' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}

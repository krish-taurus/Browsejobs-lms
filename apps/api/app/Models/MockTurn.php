<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One exchange in a mock interview: an interviewer question or a candidate
 * answer (mirrors the tutor_messages shape).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $mock_interview_id
 * @property string $role
 * @property string $body
 */
class MockTurn extends Model
{
    use BelongsToTenant;

    public const ROLE_INTERVIEWER = 'interviewer';

    public const ROLE_CANDIDATE = 'candidate';

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'mock_interview_id', 'role', 'body'];
}

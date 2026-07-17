<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A milestone badge (PRD §6.16 — First Mock Done, 7-Day Streak, Module Ace…).
 * Definitions live in config/points.php; one of each per student.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property string $badge
 * @property Carbon $awarded_at
 */
class StudentBadge extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'user_id', 'badge', 'awarded_at'];

    public function label(): string
    {
        return (string) config("points.badges.{$this->badge}", $this->badge);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['awarded_at' => 'datetime'];
    }
}

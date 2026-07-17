<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * The candidate's own CV facts (PRD §6.7): experience, personal projects,
 * education and links — imported from their uploaded CV or hand-edited.
 * One per student; every generation merges this with platform evidence.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property array<string, mixed> $data
 * @property string|null $uploaded_filename
 */
class CvProfile extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'user_id', 'data', 'uploaded_filename'];

    /** The empty shape the editor and merger both rely on. */
    public const EMPTY = [
        'summary' => null,
        'skills' => [],
        'experience' => [],   // [{title, company, period, bullets[]}]
        'projects' => [],     // [{name, bullets[]}]
        'education' => [],    // [{name, detail}]
        'certifications' => [],
        'links' => [],        // [{label, url}]
    ];

    /**
     * @return array<string, mixed>
     */
    public static function dataFor(User $student): array
    {
        $data = (array) (self::query()->where('user_id', $student->id)->value('data') ?? []);

        return array_merge(self::EMPTY, $data);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}

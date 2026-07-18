<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A Career+ booster output (LinkedIn optimisation / GitHub portfolio / prep pack),
 * kept as the latest per student per kind (PRD §6.20).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int $user_id
 * @property string $kind
 * @property array<string, mixed> $content
 * @property string $content_source
 */
class CareerBooster extends Model
{
    use BelongsToTenant;

    public const KIND_LINKEDIN = 'linkedin';

    public const KIND_GITHUB = 'github';

    public const KIND_PREP_PACK = 'prep_pack';

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'user_id', 'kind', 'content', 'content_source'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['content' => 'array'];
    }
}

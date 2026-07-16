<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable staff reply snippet (PRD §6.13), optionally scoped to a category.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $title
 * @property string $body
 * @property string|null $category
 * @property bool $active
 */
class CannedResponse extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'title', 'body', 'category', 'active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}

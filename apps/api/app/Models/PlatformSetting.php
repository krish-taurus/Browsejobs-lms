<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One platform integration setting (a credential or a provider choice). Encrypted at
 * rest — the value is never stored or logged in plaintext. Platform-global (no tenant).
 *
 * @property int $id
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property int|null $updated_by
 */
class PlatformSetting extends Model
{
    /** @var list<string> */
    protected $fillable = ['group', 'key', 'value', 'updated_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }
}

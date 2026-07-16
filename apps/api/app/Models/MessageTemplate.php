<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageCategory;
use App\Enums\MessageChannel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A message template (PRD §6.9). One row per (key, channel).
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $key
 * @property MessageChannel $channel
 * @property MessageCategory $category
 * @property string|null $name
 * @property string|null $subject
 * @property string $body
 * @property string $locale
 * @property bool $active
 */
class MessageTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'key', 'channel', 'category', 'name', 'subject', 'body', 'locale', 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'category' => MessageCategory::class,
            'active' => 'boolean',
        ];
    }
}

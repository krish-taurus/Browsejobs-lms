<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A keyed JSON content blob for the public site (hero copy, per-page SEO, …),
 * edited from the CRM and served via GET /api/v1/site-content.
 */
class SiteContent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'key', 'data'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}

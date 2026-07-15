<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A marketing lead captured from the public site (masterclass, counselling,
 * bootcamp, contact, waitlist). Feeds the P2.1 CRM.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $lead_type
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string|null $course_slug
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property Carbon $consented_at
 */
class Lead extends Model
{
    use BelongsToTenant;

    public const TYPES = ['masterclass', 'counselling', 'bootcamp', 'contact', 'waitlist'];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id', 'lead_type', 'name', 'phone', 'email', 'course_slug', 'message',
        'utm_source', 'utm_medium', 'utm_campaign', 'page', 'ip',
        'consented_at', 'consent_version', 'crm_synced',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'crm_synced' => 'boolean',
        ];
    }
}

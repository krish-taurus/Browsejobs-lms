<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a client's hiring chat on WhatsApp, either way.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $employer_whatsapp_link_id
 * @property int|null $interview_invite_id
 * @property string $phone
 * @property string $direction
 * @property string $body
 * @property string|null $wamid
 * @property string|null $media_id
 * @property string $status
 */
final class EmployerWhatsappMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'employer_whatsapp_link_id', 'interview_invite_id', 'phone', 'direction', 'body', 'wamid', 'media_id', 'status'];

    /** @return BelongsTo<EmployerWhatsappLink, $this> */
    public function link(): BelongsTo
    {
        return $this->belongsTo(EmployerWhatsappLink::class, 'employer_whatsapp_link_id');
    }
}

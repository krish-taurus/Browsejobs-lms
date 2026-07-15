<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every tenant-owned model. Adds the global {@see TenantScope} so
 * reads are constrained to the active tenant, and auto-stamps `tenant_id` on
 * create from {@see TenantContext} so callers never have to set it by hand.
 *
 * @property int|null $tenant_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if (! empty($model->getAttribute('tenant_id'))) {
                return;
            }

            $context = app(TenantContext::class);

            if ($context->has()) {
                $model->setAttribute('tenant_id', $context->id());
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

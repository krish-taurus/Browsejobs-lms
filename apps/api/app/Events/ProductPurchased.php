<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ProductPurchase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an in-app purchase is captured + granted (PRD §6.17).
 */
final class ProductPurchased
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ProductPurchase $purchase,
    ) {}
}

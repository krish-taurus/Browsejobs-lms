<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Receipt;
use App\Models\Tenant;
use App\Support\Receipts\ReceiptRenderer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Renders + stores a captured payment's branded GST receipt (PRD §6.8; PDF
 * renders are queued jobs per CLAUDE.md). Idempotent: skips an already-rendered
 * receipt.
 */
final class RenderReceipt implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $receiptId) {}

    public function handle(ReceiptRenderer $renderer): void
    {
        $receipt = Receipt::query()->withoutGlobalScopes()->find($this->receiptId);
        if ($receipt === null || $receipt->status === Receipt::STATUS_RENDERED) {
            return;
        }

        $tenant = Tenant::query()->find($receipt->tenant_id);
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($receipt, $renderer): void {
            $path = $renderer->render($receipt);

            $receipt->storage_path = $path;
            $receipt->status = Receipt::STATUS_RENDERED;
            $receipt->save();
        });
    }
}

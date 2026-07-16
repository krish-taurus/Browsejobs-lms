<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ProductPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a purchase's GST invoice to branded HTML on the documents disk (PRD
 * §6.17). Mirrors RenderReceipt for fee instalments; reuses the receipts styling.
 */
final class RenderPurchaseReceipt implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $purchaseId) {}

    public function handle(ViewFactory $view): void
    {
        $purchase = ProductPurchase::query()->withoutGlobalScopes()->with(['student', 'product', 'tenant'])->find($this->purchaseId);
        if ($purchase === null || $purchase->receipt_number === null) {
            return;
        }

        $rupees = static fn (int $paise): string => number_format($paise / 100, 2);

        $html = $view->make('receipts.purchase', [
            'number' => $purchase->receipt_number,
            'date' => $purchase->captured_at ?? $purchase->created_at,
            'tenantName' => $purchase->tenant?->name ?? 'BrowseJobs',
            'studentName' => $purchase->student?->name ?? 'Student',
            'itemName' => $purchase->product?->name ?? $purchase->sku,
            'taxable' => $rupees($purchase->taxable_paise),
            'cgst' => $rupees($purchase->cgst_paise),
            'sgst' => $rupees($purchase->sgst_paise),
            'total' => $rupees($purchase->total_paise),
        ])->render();

        $path = "receipts/{$purchase->tenant_id}/{$purchase->receipt_number}.html";
        Storage::disk('s3')->put($path, $html);

        $purchase->update(['receipt_path' => $path]);
    }
}

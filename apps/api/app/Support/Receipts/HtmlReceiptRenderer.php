<?php

declare(strict_types=1);

namespace App\Support\Receipts;

use App\Models\Receipt;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the GST receipt as branded HTML and stores it on the documents disk.
 * The WeasyPrint PDF step swaps in behind {@see ReceiptRenderer} later.
 */
final class HtmlReceiptRenderer implements ReceiptRenderer
{
    public const DISK = 's3';

    public function __construct(private readonly ViewFactory $view) {}

    public function render(Receipt $receipt): string
    {
        $receipt->loadMissing(['payment.instalment', 'feePlan.student', 'feePlan.batch.course', 'tenant']);

        $rupees = static fn (int $paise): string => number_format($paise / 100, 2);

        $html = $this->view->make('receipts.gst', [
            'receipt' => $receipt,
            'tenantName' => $receipt->tenant?->name ?? 'BrowseJobs',
            'studentName' => $receipt->feePlan?->student?->name ?? 'Student',
            'courseName' => $receipt->feePlan?->batch?->course?->name ?? '—',
            'seq' => $receipt->payment?->instalment?->seq ?? 1,
            'taxable' => $rupees($receipt->taxable_paise),
            'cgst' => $rupees($receipt->cgst_paise),
            'sgst' => $rupees($receipt->sgst_paise),
            'total' => $rupees($receipt->total_paise),
        ])->render();

        $path = "receipts/{$receipt->tenant_id}/{$receipt->number}.html";
        Storage::disk(self::DISK)->put($path, $html);

        return $path;
    }
}

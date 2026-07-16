<?php

declare(strict_types=1);

namespace App\Support\Receipts;

use App\Models\Receipt;

/**
 * Renders a branded GST tax invoice for a captured payment and returns the
 * storage path (PRD §6.8). P2.2 renders branded HTML; the WeasyPrint pipeline
 * (PRD stack) will binarize to PDF later behind this same interface. Called only
 * from the queued RenderReceipt job.
 */
interface ReceiptRenderer
{
    public function render(Receipt $receipt): string;
}

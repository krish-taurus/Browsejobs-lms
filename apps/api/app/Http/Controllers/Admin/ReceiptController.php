<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Support\Receipts\HtmlReceiptRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Streams a rendered GST receipt (PRD §6.8). Gated by `can:manage-fees`.
 */
final class ReceiptController extends Controller
{
    public function download(Receipt $receipt): SymfonyResponse
    {
        if ($receipt->storage_path === null
            || ! Storage::disk(HtmlReceiptRenderer::DISK)->exists($receipt->storage_path)) {
            abort(404, 'Receipt not rendered yet.');
        }

        $body = Storage::disk(HtmlReceiptRenderer::DISK)->get($receipt->storage_path);

        return new Response($body, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.str_replace('/', '-', $receipt->number).'.html"',
        ]);
    }
}

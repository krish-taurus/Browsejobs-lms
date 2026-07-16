<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\BulkSendPaymentLinks;
use App\Actions\Payments\CreateInstalmentOrder;
use App\Actions\Payments\SendPaymentLink;
use App\Http\Controllers\Controller;
use App\Http\Resources\InstalmentResource;
use App\Models\Batch;
use App\Models\Instalment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Personalized payment links + Razorpay orders for instalments (PRD §6.8).
 * Gated by `can:manage-fees`.
 */
final class PaymentLinkController extends Controller
{
    public function store(Instalment $instalment, SendPaymentLink $send): JsonResponse
    {
        $send->handle($instalment);

        return (new InstalmentResource($instalment->refresh()))->response();
    }

    public function order(Instalment $instalment, CreateInstalmentOrder $create): JsonResponse
    {
        $payment = $create->handle($instalment);

        return response()->json(['data' => [
            'razorpay_order_id' => $payment->razorpay_order_id,
            'amount_paise' => $payment->amount_paise,
            'currency' => 'INR',
        ]]);
    }

    public function bulk(Request $request, BulkSendPaymentLinks $bulk): JsonResponse
    {
        $batch = Batch::query()->findOrFail($request->integer('batch_id'));
        $count = $bulk->handle($batch);

        return response()->json(['data' => ['links_created' => $count]]);
    }
}

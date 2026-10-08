<?php

declare(strict_types=1);

namespace App\Http\Controllers\Enquiries;

use App\Actions\Enquiries\CaptureEnquiry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enquiries\StoreEnquiryRequest;
use Illuminate\Http\JsonResponse;

/**
 * Public enquiry capture. Tenant comes from the host. No message is sent
 * back to the person who submitted.
 */
final class EnquiryController extends Controller
{
    public function store(StoreEnquiryRequest $request, CaptureEnquiry $capture): JsonResponse
    {
        $enquiry = $capture->handle(
            $request->safe()->except(['consent', 'website', 'form_started_at']),
            $request,
        );

        return response()->json(['status' => 'received', 'id' => $enquiry->id], 201);
    }
}

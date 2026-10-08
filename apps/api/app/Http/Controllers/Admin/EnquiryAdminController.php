<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enquiries\UpdateEnquiryRequest;
use App\Http\Resources\EnquiryResource;
use App\Models\Enquiry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff inbox for employer and course enquiries. Gated by manage-leads.
 * Route-model binding is tenant-scoped, so a foreign id 404s.
 */
final class EnquiryAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $enquiries = $this->filtered($request)
            ->orderByDesc('id')
            ->paginate(25);

        return EnquiryResource::collection($enquiries)->response();
    }

    public function update(UpdateEnquiryRequest $request, Enquiry $enquiry): JsonResponse
    {
        $enquiry->update(['status' => $request->string('status')->toString()]);

        return (new EnquiryResource($enquiry->fresh()))->response();
    }

    public function export(Request $request): Response
    {
        $rows = $this->filtered($request)->orderByDesc('id')->limit(5000)->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'id', 'type', 'status', 'name', 'email', 'phone', 'company', 'company_size',
            'roles', 'city', 'timeline', 'course_slug', 'learner_status', 'preferred_time',
            'message', 'utm_source', 'utm_medium', 'utm_campaign', 'referrer', 'landing_page',
            'ip_hash', 'created_at',
        ]);

        foreach ($rows as $enquiry) {
            fputcsv($handle, [
                $enquiry->id,
                $enquiry->type,
                $enquiry->status,
                $enquiry->name,
                $enquiry->email,
                $enquiry->phone,
                $enquiry->company,
                $enquiry->company_size,
                $enquiry->roles,
                $enquiry->city,
                $enquiry->timeline,
                $enquiry->course_slug,
                $enquiry->learner_status,
                $enquiry->preferred_time,
                $enquiry->message,
                $enquiry->utm_source,
                $enquiry->utm_medium,
                $enquiry->utm_campaign,
                $enquiry->referrer,
                $enquiry->landing_page,
                $enquiry->ip_hash,
                $enquiry->created_at?->toIso8601String(),
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="enquiries.csv"',
        ]);
    }

    /**
     * @return Builder<Enquiry>
     */
    private function filtered(Request $request): Builder
    {
        return Enquiry::query()
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn (Builder $w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('company', 'like', $term));
            });
    }
}

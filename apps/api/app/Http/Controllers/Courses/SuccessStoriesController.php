<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courses;

use App\Http\Controllers\Controller;
use App\Models\PlacementStory;
use Illuminate\Http\JsonResponse;

/**
 * Public "success stories" for the home page (Platform Spec §3): a handful of
 * published placement cards across every course. Real stories require consent;
 * labelled samples don't (they're illustrative, and the UI marks them). Tenant
 * resolved by domain.
 */
final class SuccessStoriesController extends Controller
{
    public function index(): JsonResponse
    {
        $stories = PlacementStory::query()
            ->with('course:id,slug,name')
            ->where('is_published', true)
            ->where(fn ($q) => $q->where('consent', true)->orWhere('is_sample', true))
            // Real, consented stories first; then samples. Stable within each.
            ->orderBy('is_sample')->orderBy('position')->orderBy('id')
            ->limit(6)
            ->get()
            ->map(fn (PlacementStory $s) => [
                'name' => $s->student_name,
                'before' => $s->before_label,
                'after' => $s->after_role,
                'package' => $s->package_label,
                'company' => $s->company_name,
                'company_color' => $s->company_color,
                'rounds' => $s->rounds,
                'quote' => $s->quote,
                'screenshot_url' => $s->screenshotUrl(),
                'is_sample' => $s->is_sample,
                'course_slug' => $s->course?->slug,
                'course_name' => $s->course?->name,
            ])
            ->all();

        return response()->json(['data' => $stories]);
    }
}

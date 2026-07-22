<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courses;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseInterviewQuestion;
use App\Models\PlacementStory;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public course-page social proof (Platform Spec §3): the course's placement
 * stories, its interview-question bank grouped by round, and its reviews.
 * Tenant is resolved by domain (route group middleware); only published +
 * consented content is served. Reviews carry no salary/company claims, so no
 * disclaimer is owed here — the stories' package figures do, and the client
 * renders the shared <Disclaimer/> beneath them.
 */
final class SocialProofController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $course = Course::query()->where('slug', $slug)->first();
        if ($course === null) {
            throw new NotFoundHttpException('Course not found.');
        }

        return response()->json([
            'data' => [
                'course' => ['slug' => $course->slug, 'name' => $course->name],
                'stories' => $this->stories($course->id),
                'question_rounds' => $this->questionRounds($course->id),
                'reviews' => $this->reviews($course->slug),
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function stories(int $courseId): array
    {
        return PlacementStory::query()
            ->where('course_id', $courseId)
            ->where('is_published', true)
            ->where('consent', true)
            ->orderBy('position')->orderBy('id')
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
            ])
            ->all();
    }

    /**
     * Questions grouped by round, in round then position order.
     *
     * @return list<array{round_no:int, round_name:string, questions:list<string>}>
     */
    private function questionRounds(int $courseId): array
    {
        return CourseInterviewQuestion::query()
            ->where('course_id', $courseId)
            ->where('is_published', true)
            ->orderBy('round_no')->orderBy('position')->orderBy('id')
            ->get()
            ->groupBy('round_no')
            ->map(fn ($rows) => [
                'round_no' => (int) $rows->first()->round_no,
                'round_name' => (string) $rows->first()->round_name,
                'questions' => $rows->pluck('question')->all(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function reviews(string $slug): array
    {
        return Review::query()
            ->where('is_active', true)
            ->where('course_slug', $slug)
            ->orderBy('sort_order')->orderByDesc('id')
            ->get()
            ->map(fn (Review $r) => [
                'name' => $r->author_name,
                'meta' => $r->author_meta,
                'rating' => $r->rating,
                'body' => $r->body,
                'source' => $r->source,
            ])
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\RealInterviewQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Interview intelligence dashboard (founder spec): what companies actually
 * focus on — topics ranked by how often they appear in APPROVED real
 * interviews (weighted by asked_count), with the actual questions grouped
 * under each topic, plus a company leaderboard. Pure aggregation over the
 * interview bank — no AI, no invented numbers.
 */
final class InterviewIntelController extends Controller
{
    private const QUESTIONS_PER_TOPIC = 10;

    public function index(Request $request): JsonResponse
    {
        $courses = Course::query()->orderBy('name')->get(['id', 'name']);
        $courseId = (int) ($request->input('course_id') ?: ($courses->first()->id ?? 0));

        $questions = RealInterviewQuestion::query()
            ->where('status', RealInterviewQuestion::STATUS_APPROVED)
            ->when($courseId > 0, fn ($q) => $q->where('course_id', $courseId))
            ->with('transcript:id,company,role_title')
            ->get(['id', 'interview_transcript_id', 'question', 'topic_tags', 'difficulty', 'round_type', 'asked_count', 'struggle_points', 'outcome']);

        $topics = [];
        $companies = [];
        foreach ($questions as $q) {
            $weight = max(1, (int) $q->asked_count);
            $company = $q->transcript?->company;
            if (filled($company)) {
                $companies[$company] = ($companies[$company] ?? 0) + $weight;
            }

            foreach ($q->topic_tags ?? [] as $tag) {
                $t = mb_strtolower(trim((string) $tag));
                if ($t === '') {
                    continue;
                }
                $topics[$t] ??= ['topic' => $t, 'frequency' => 0, 'questions' => 0, 'struggles' => 0, 'items' => []];
                $topics[$t]['frequency'] += $weight;
                $topics[$t]['questions']++;
                if (filled($q->struggle_points) || in_array($q->outcome, ['rejected', 'no_offer', 'failed'], true)) {
                    $topics[$t]['struggles']++;
                }
                $topics[$t]['items'][] = [
                    'question' => $q->question,
                    'asked_count' => $weight,
                    'difficulty' => $q->difficulty,
                    'round_type' => $q->round_type,
                    'company' => $company,
                ];
            }
        }

        usort($topics, fn ($a, $b) => $b['frequency'] <=> $a['frequency']);
        $topics = array_map(function (array $t): array {
            usort($t['items'], fn ($a, $b) => $b['asked_count'] <=> $a['asked_count']);
            $t['items'] = array_slice($t['items'], 0, self::QUESTIONS_PER_TOPIC);

            return $t;
        }, array_values($topics));

        arsort($companies);

        return response()->json(['data' => [
            'courses' => $courses->map(fn (Course $c) => ['id' => $c->id, 'name' => $c->name]),
            'course_id' => $courseId,
            'sample' => $questions->count(),
            'topics' => array_slice($topics, 0, 30),
            'companies' => array_map(
                fn ($name, $freq) => ['company' => $name, 'frequency' => $freq],
                array_keys(array_slice($companies, 0, 15, true)),
                array_slice($companies, 0, 15, true),
            ),
        ]]);
    }
}

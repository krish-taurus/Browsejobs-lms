<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\SalaryBenchmark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Curated salary benchmark dataset admin (PRD §6.21). Placement team maintains
 * the role/city/experience CTC percentiles. Figures accepted as annual LPA and
 * stored in paise (never floats for currency).
 */
final class SalaryBenchmarkController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = SalaryBenchmark::query()
            ->with('course:id,name')
            ->orderBy('role_title')
            ->orderBy('city')
            ->get()
            ->map(fn (SalaryBenchmark $b) => [
                'id' => $b->id,
                'course' => $b->course?->name,
                'course_id' => $b->course_id,
                'role_title' => $b->role_title,
                'city' => $b->city,
                'experience_band' => $b->experience_band,
                'p25_lpa' => $this->toLpa($b->p25_ctc_paise),
                'p50_lpa' => $this->toLpa($b->p50_ctc_paise),
                'p75_lpa' => $this->toLpa($b->p75_ctc_paise),
                'source' => $b->source,
                'effective_on' => $b->effective_on?->toDateString(),
            ]);

        return response()->json(['data' => [
            'courses' => Course::query()->orderBy('name')->get(['id', 'name']),
            'benchmarks' => $rows,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $benchmark = SalaryBenchmark::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'course_id' => $data['course_id'] ?? null,
            'role_title' => $data['role_title'],
            'city' => $data['city'],
            'experience_band' => $data['experience_band'],
            'p25_ctc_paise' => $this->toPaise($data['p25_lpa']),
            'p50_ctc_paise' => $this->toPaise($data['p50_lpa']),
            'p75_ctc_paise' => $this->toPaise($data['p75_lpa']),
            'source' => $data['source'] ?? null,
            'effective_on' => $data['effective_on'] ?? null,
        ]);

        return response()->json(['data' => ['id' => $benchmark->id]], 201);
    }

    public function update(Request $request, SalaryBenchmark $benchmark): JsonResponse
    {
        $data = $this->validated($request);

        $benchmark->update([
            'course_id' => $data['course_id'] ?? null,
            'role_title' => $data['role_title'],
            'city' => $data['city'],
            'experience_band' => $data['experience_band'],
            'p25_ctc_paise' => $this->toPaise($data['p25_lpa']),
            'p50_ctc_paise' => $this->toPaise($data['p50_lpa']),
            'p75_ctc_paise' => $this->toPaise($data['p75_lpa']),
            'source' => $data['source'] ?? null,
            'effective_on' => $data['effective_on'] ?? null,
        ]);

        return response()->json(['data' => ['id' => $benchmark->id]]);
    }

    public function destroy(SalaryBenchmark $benchmark): JsonResponse
    {
        $benchmark->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'course_id' => ['nullable', 'integer'],
            'role_title' => ['required', 'string', 'max:190'],
            'city' => ['required', 'string', 'max:120'],
            'experience_band' => ['required', Rule::in(SalaryBenchmark::BANDS)],
            'p25_lpa' => ['required', 'numeric', 'min:0', 'max:1000'],
            'p50_lpa' => ['required', 'numeric', 'min:0', 'max:1000'],
            'p75_lpa' => ['required', 'numeric', 'min:0', 'max:1000'],
            'source' => ['nullable', 'string', 'max:190'],
            'effective_on' => ['nullable', 'date'],
        ]);
    }

    /** LPA (lakhs per annum) → paise. 1 LPA = 100000 INR = 10000000 paise. */
    private function toPaise(mixed $lpa): int
    {
        return (int) round(((float) $lpa) * 100_000 * 100);
    }

    private function toLpa(int $paise): float
    {
        return round($paise / (100_000 * 100), 2);
    }
}

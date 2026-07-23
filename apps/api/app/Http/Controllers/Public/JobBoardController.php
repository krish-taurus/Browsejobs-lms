<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\EntitlementFeature;
use App\Http\Controllers\Controller;
use App\Models\JobFeedItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

/**
 * The public job board (ADR 0048): live openings from the last seven days,
 * refreshed by the scheduled feed sync. Tenant by domain, no auth. Deliberately
 * a teaser — match %, confidence, full prep questions, tailored CVs and mocks
 * all require an account, so every card funnels to registration. Descriptions
 * are truncated and apply URLs withheld: the board's job is to start a journey,
 * not to be a link farm.
 */
final class JobBoardController extends Controller
{
    /** The founder's freshness rule: the public board shows the last 7 days. */
    private const WINDOW_DAYS = 7;

    private const LIMIT = 120;

    public function index(): JsonResponse
    {
        $items = JobFeedItem::query()
            ->where('status', JobFeedItem::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where('posted_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->orderByDesc('posted_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (JobFeedItem $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'company' => $i->company,
                'location' => $i->location,
                'work_mode' => $i->work_mode,
                'role_title' => $i->role_title,
                'seniority' => $i->seniority,
                'skills' => array_slice($i->extracted_skills ?? [], 0, 8),
                'summary' => mb_substr(trim($i->description), 0, 280),
                'posted_at' => $i->posted_at?->toDateString(),
                // Teaser: up to three prep questions when they're already generated,
                // plus honest provenance counts (never invented figures).
                'teaser_questions' => array_slice(
                    array_map(fn ($q) => (string) ($q['question'] ?? ''), (array) ($i->prep_questions ?? [])),
                    0,
                    3,
                ),
                'question_count' => count((array) ($i->prep_questions ?? [])),
                'real_question_count' => count(array_filter(
                    (array) ($i->prep_questions ?? []),
                    fn ($q) => ($q['source'] ?? null) === 'real',
                )),
            ]);

        // Server-owned kit prices for the public hooks (CLAUDE.md: figures are
        // never client-sent). Absent until the products are seeded.
        $offers = Product::query()
            ->where('feature', EntitlementFeature::JobKit->value)
            ->where('active', true)
            ->orderBy('price_paise')
            ->get(['sku', 'name', 'price_paise'])
            ->map(fn (Product $p) => ['sku' => $p->sku, 'name' => $p->name, 'price_paise' => $p->price_paise]);

        return response()->json(['data' => [
            'jobs' => $items,
            'window_days' => self::WINDOW_DAYS,
            'kit_offers' => $offers,
        ]]);
    }
}

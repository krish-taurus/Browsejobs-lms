<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cv;

use App\Actions\Cv\GenerateCv;
use App\Enums\EntitlementFeature;
use App\Http\Controllers\Controller;
use App\Models\CvDocument;
use App\Models\Product;
use App\Support\Cv\AtsReport;
use App\Support\Entitlements\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Student CV suite (PRD §6.7): versions, credit-gated generation and JD
 * tailoring (free manual edits), the deterministic ATS panel, and share
 * links. Under auth:sanctum without tenant.user — wrapped in the student's
 * tenant context, scoped to their user id.
 */
final class CvController extends Controller
{
    public function __construct(
        private readonly GenerateCv $generate,
        private readonly AtsReport $ats,
        private readonly EntitlementService $entitlements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $cvs = CvDocument::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('version')
                ->get();

            return response()->json(['data' => [
                'credits' => $this->entitlements->balance($request->user(), EntitlementFeature::Cv->value),
                'topups' => $this->topups(),
                'latest' => $cvs->first() !== null ? $this->row($cvs->first()) : null,
                'versions' => $cvs->map(fn (CvDocument $cv) => [
                    'id' => $cv->id,
                    'version' => $cv->version,
                    'source' => $cv->source,
                    'status' => $cv->status,
                    'created_at' => $cv->created_at?->toIso8601String(),
                ]),
            ]]);
        });
    }

    public function show(Request $request, int $cv): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, fn (): JsonResponse => response()->json([
            'data' => $this->row($this->owned($request, $cv)),
        ]));
    }

    /** New generation (or JD-tailored version) — consumes one credit. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jd' => ['nullable', 'string', 'max:20000'],
            'base_id' => ['nullable', 'integer'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $validated): JsonResponse {
            $student = $request->user();
            $jd = isset($validated['jd']) && trim((string) $validated['jd']) !== '' ? (string) $validated['jd'] : null;

            try {
                $this->entitlements->consumeCredits($student, EntitlementFeature::Cv->value, 1, 'cv_generation');
            } catch (ValidationException) {
                return response()->json([
                    'error' => [
                        'code' => 'no_cv_credits',
                        'message' => 'You are out of CV generations.',
                        'topups' => $this->topups(),
                    ],
                ], 402);
            }

            // A spent AI budget degrades to the deterministic assembler inside
            // GenerateCv — the credit always buys a CV, never an error.
            $cv = $this->generate->handle(
                $student,
                $jd !== null ? CvDocument::SOURCE_TAILORED : CvDocument::SOURCE_MANUAL,
                $jd,
            );

            return response()->json(['data' => $this->row($cv)], 201);
        });
    }

    /** Manual text edits are free (PRD §6.7). */
    public function update(Request $request, int $cv): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'array'],
            'content.headline' => ['nullable', 'string', 'max:200'],
            'content.summary' => ['nullable', 'string', 'max:2000'],
            'content.skills' => ['sometimes', 'array', 'max:30'],
            'content.projects' => ['sometimes', 'array', 'max:15'],
            'content.education' => ['sometimes', 'array', 'max:10'],
            'content.certifications' => ['sometimes', 'array', 'max:15'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $cv, $validated): JsonResponse {
            $model = $this->owned($request, $cv);

            // Contact details always come from the profile, and edits reset
            // approval — the placement officer approved the previous text.
            $content = array_merge($model->content, $validated['content'], [
                'name' => $model->content['name'] ?? null,
                'email' => $model->content['email'] ?? null,
                'phone' => $model->content['phone'] ?? null,
            ]);

            $model->update([
                'content' => $content,
                'ats' => $this->ats->for($content, $model->jd_excerpt),
                'status' => CvDocument::STATUS_DRAFT,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            return response()->json(['data' => $this->row($model->refresh())]);
        });
    }

    /** Re-score against a pasted JD — deterministic, free, unlimited. */
    public function atsCheck(Request $request, int $cv): JsonResponse
    {
        $validated = $request->validate(['jd' => ['nullable', 'string', 'max:20000']]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $cv, $validated): JsonResponse {
            $model = $this->owned($request, $cv);
            $report = $this->ats->for($model->content, $validated['jd'] ?? null);
            $model->update(['ats' => $report]);

            return response()->json(['data' => $report]);
        });
    }

    public function share(Request $request, int $cv): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $cv): JsonResponse {
            $model = $this->owned($request, $cv);

            if ($model->share_token === null) {
                $model->update(['share_token' => Str::random(48)]);
            }

            return response()->json(['data' => [
                'share_url' => rtrim((string) config('app.frontend_url'), '/').'/cv/shared/'.$model->refresh()->share_token,
            ]]);
        });
    }

    public function unshare(Request $request, int $cv): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $cv): JsonResponse {
            $this->owned($request, $cv)->update(['share_token' => null]);

            return response()->json(['ok' => true]);
        });
    }

    /** Public share view — token is the only key; no auth, no tenant host. */
    public function shared(string $token): JsonResponse
    {
        $cv = CvDocument::query()->withoutGlobalScopes()
            ->where('share_token', $token)
            ->firstOrFail();

        return response()->json(['data' => [
            'title' => $cv->title,
            'content' => $cv->content,
            'approved' => $cv->status === CvDocument::STATUS_APPROVED,
            'updated_at' => $cv->updated_at?->toIso8601String(),
        ]]);
    }

    private function owned(Request $request, int $id): CvDocument
    {
        return CvDocument::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(CvDocument $cv): array
    {
        return [
            'id' => $cv->id,
            'version' => $cv->version,
            'title' => $cv->title,
            'source' => $cv->source,
            'content_source' => $cv->content_source,
            'status' => $cv->status,
            'content' => $cv->content,
            'ats' => $cv->ats,
            'jd_excerpt' => $cv->jd_excerpt,
            'share_token' => $cv->share_token,
            'created_at' => $cv->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topups(): array
    {
        return Product::query()
            ->where('feature', EntitlementFeature::Cv->value)
            ->where('active', true)
            ->orderBy('price_paise')
            ->get(['id', 'sku', 'name', 'price_paise', 'grant_amount'])
            ->map(fn (Product $p) => [
                'product_id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'price_paise' => $p->price_paise,
                'generations' => $p->grant_amount,
            ])
            ->all();
    }
}

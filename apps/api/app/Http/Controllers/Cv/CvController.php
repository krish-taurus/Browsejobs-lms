<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cv;

use App\Actions\Cv\GenerateCv;
use App\Enums\AiPurpose;
use App\Enums\EntitlementFeature;
use App\Http\Controllers\Controller;
use App\Models\CvDocument;
use App\Models\CvProfile;
use App\Models\Product;
use App\Services\AI\AiBudgetExceeded;
use App\Services\AI\AiGateway;
use App\Support\Cv\AtsReport;
use App\Support\Entitlements\EntitlementService;
use App\Support\Files\DocxExtractor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        private readonly AiGateway $gateway,
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

    /**
     * Import the candidate's existing CV (file or pasted text): extract the
     * text, AI-parse it into the profile — extraction only, never invention.
     * Free (onboarding, not a generation).
     */
    public function importCv(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required_without:text', 'nullable', 'file', 'max:5120'],
            'text' => ['required_without:file', 'nullable', 'string', 'max:60000'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request): JsonResponse {
            $filename = null;
            $text = (string) $request->input('text', '');

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filename = $file->getClientOriginalName();
                $extension = strtolower($file->getClientOriginalExtension());
                $contents = (string) $file->get();

                $text = match (true) {
                    in_array($extension, ['txt', 'md', 'rtf'], true) => $contents,
                    $extension === 'docx' => (new DocxExtractor)->extract($contents),
                    default => '',
                };

                abort_if(trim($text) === '', 422, "Could not read .{$extension} — upload a .docx or .txt, or paste the text.");
            }

            abort_if(trim($text) === '', 422, 'The CV text is empty.');

            try {
                $result = $this->gateway->complete($request->user(), AiPurpose::Cv, 'cv_parse', 1, [
                    'cv_text' => mb_substr($text, 0, 30000),
                ], ['max_tokens' => 2500]);
                $parsed = json_decode(trim($result->text), true);
            } catch (AiBudgetExceeded) {
                return response()->json([
                    'error' => ['code' => 'ai_budget_exceeded', 'message' => 'Daily AI limit reached — try again tomorrow.'],
                ], 429);
            } catch (Throwable) {
                // Transport down — never a 500 for the student.
                abort(422, 'CV parsing is unavailable right now — add your details manually in the profile editor below.');
            }

            abort_unless(is_array($parsed), 422, 'Could not parse that CV — paste the text and edit your profile manually.');

            $data = array_merge(CvProfile::EMPTY, array_intersect_key($parsed, CvProfile::EMPTY));

            CvProfile::query()->updateOrCreate(
                ['tenant_id' => $request->user()->tenant_id, 'user_id' => $request->user()->id],
                ['data' => $data, 'uploaded_filename' => $filename],
            );

            return response()->json(['data' => $data], 201);
        });
    }

    public function profile(Request $request): JsonResponse
    {
        return app(TenantContext::class)->run($request->user()->tenant, fn (): JsonResponse => response()->json([
            'data' => [
                'profile' => CvProfile::dataFor($request->user()),
                'uploaded_filename' => CvProfile::query()->where('user_id', $request->user()->id)->value('uploaded_filename'),
            ],
        ]));
    }

    /** Hand-edit the profile facts (custom projects, experience…) — free. */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'summary' => ['nullable', 'string', 'max:2000'],
            'skills' => ['present', 'array', 'max:40'],
            'skills.*' => ['string', 'max:80'],
            'experience' => ['present', 'array', 'max:15'],
            'experience.*.title' => ['required', 'string', 'max:150'],
            'experience.*.company' => ['required', 'string', 'max:150'],
            'experience.*.period' => ['nullable', 'string', 'max:60'],
            'experience.*.bullets' => ['present', 'array', 'max:8'],
            'experience.*.bullets.*' => ['string', 'max:300'],
            'projects' => ['present', 'array', 'max:15'],
            'projects.*.name' => ['required', 'string', 'max:150'],
            'projects.*.bullets' => ['present', 'array', 'max:8'],
            'projects.*.bullets.*' => ['string', 'max:300'],
            'education' => ['present', 'array', 'max:10'],
            'education.*.name' => ['required', 'string', 'max:150'],
            'education.*.detail' => ['nullable', 'string', 'max:200'],
            'certifications' => ['present', 'array', 'max:15'],
            'certifications.*' => ['string', 'max:150'],
            'links' => ['present', 'array', 'max:6'],
            'links.*.label' => ['required', 'string', 'max:40'],
            'links.*.url' => ['required', 'url', 'max:300'],
        ]);

        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $validated): JsonResponse {
            $data = array_merge(CvProfile::EMPTY, $validated);

            CvProfile::query()->updateOrCreate(
                ['tenant_id' => $request->user()->tenant_id, 'user_id' => $request->user()->id],
                ['data' => $data],
            );

            return response()->json(['data' => $data]);
        });
    }

    /** Plain-text export — the format every ATS on earth parses. */
    public function atsText(Request $request, int $cv): Response
    {
        return app(TenantContext::class)->run($request->user()->tenant, function () use ($request, $cv): Response {
            $model = $this->owned($request, $cv);
            $c = $model->content;

            $lines = [mb_strtoupper((string) ($c['name'] ?? '')), (string) ($c['headline'] ?? '')];
            $lines[] = trim(implode(' | ', array_filter([(string) ($c['email'] ?? ''), (string) ($c['phone'] ?? '')])));
            foreach ((array) ($c['links'] ?? []) as $link) {
                $lines[] = trim(($link['label'] ?? '').': '.($link['url'] ?? ''), ': ');
            }

            $section = function (string $title) use (&$lines): void {
                $lines[] = '';
                $lines[] = mb_strtoupper($title);
            };

            if (! empty($c['summary'])) {
                $section('Summary');
                $lines[] = (string) $c['summary'];
            }
            if (! empty($c['skills'])) {
                $section('Skills');
                $lines[] = implode(', ', (array) $c['skills']);
            }
            if (! empty($c['experience'])) {
                $section('Experience');
                foreach ((array) $c['experience'] as $e) {
                    $lines[] = trim(($e['title'] ?? '').' — '.($e['company'] ?? '').'  '.($e['period'] ?? ''));
                    foreach ((array) ($e['bullets'] ?? []) as $b) {
                        $lines[] = '- '.$b;
                    }
                }
            }
            if (! empty($c['projects'])) {
                $section('Projects');
                foreach ((array) $c['projects'] as $p) {
                    $lines[] = (string) ($p['name'] ?? '');
                    foreach ((array) ($p['bullets'] ?? []) as $b) {
                        $lines[] = '- '.$b;
                    }
                }
            }
            if (! empty($c['education'])) {
                $section('Education');
                foreach ((array) $c['education'] as $e) {
                    $lines[] = trim(($e['name'] ?? '').' — '.($e['detail'] ?? ''), ' —');
                }
            }
            if (! empty($c['certifications'])) {
                $section('Certifications');
                foreach ((array) $c['certifications'] as $x) {
                    $lines[] = '- '.$x;
                }
            }

            return response(implode("\r\n", $lines)."\r\n", 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Content-Disposition' => 'attachment; filename=cv-v'.$model->version.'.txt',
            ]);
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
            'content.experience' => ['sometimes', 'array', 'max:15'],
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

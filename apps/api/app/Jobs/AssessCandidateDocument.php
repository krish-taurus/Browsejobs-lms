<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Models\CandidateDocument;
use App\Services\AI\AiGateway;
use App\Support\Files\DocxExtractor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Assess one uploaded document (PRD-E F9) — one job per file, so a candidate
 * who uploads four gets four independent verdicts a reviewer can act on
 * separately.
 *
 * Two things this job will not do:
 *
 *  - It never settles the verification check. The output is advice for the
 *    ops reviewer. A verified badge is a claim made to employers about a
 *    person, and a model does not get to make it (CLAUDE.md AI service layer,
 *    PRD §7 human approval).
 *  - It never assesses a file it could not read. There is no PDF extractor
 *    and no OCR in this stack, so a PDF or a photo is marked unreadable and
 *    sent to a human. Running the model on a filename would produce a verdict
 *    that looks identical to a real one.
 */
final class AssessCandidateDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public int $documentId) {}

    public function handle(AiGateway $gateway, DocxExtractor $docx): void
    {
        $document = CandidateDocument::withoutGlobalScopes()->find($this->documentId);

        if ($document === null || $document->assessed_at !== null) {
            return;
        }

        $candidate = $document->candidate;

        if ($candidate === null) {
            return;
        }

        app(TenantContext::class)->run($candidate->tenant, function () use ($document, $candidate, $gateway, $docx): void {
            $text = $this->textOf($document, $docx);

            if ($text === null) {
                $document->forceFill([
                    'extract_status' => CandidateDocument::EXTRACT_UNREADABLE,
                    'ai_verdict' => null,
                    'assessed_at' => now(),
                ])->save();

                return;
            }

            try {
                $result = $gateway->complete($candidate, AiPurpose::Report, 'document_verify', 1, [
                    'kind' => $document->label(),
                    'filename' => $document->original_name,
                    'candidate_name' => (string) $candidate->name,
                    'document_text' => mb_substr($text, 0, 20000),
                ], ['max_tokens' => 1200]);

                $verdict = json_decode(trim($result->text), true);
            } catch (Throwable $e) {
                Log::info('Document assessment unavailable', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);

                // Left unassessed on purpose: the reviewer sees "not assessed
                // yet" and reads it themselves, rather than an empty verdict
                // that looks like a clean one.
                return;
            }

            if (! is_array($verdict)) {
                return;
            }

            $document->forceFill([
                'extract_status' => CandidateDocument::EXTRACT_DONE,
                'ai_verdict' => $this->sanitise($verdict),
                'assessed_at' => now(),
            ])->save();
        });
    }

    /**
     * The document's text, or null when this stack cannot read the format.
     */
    private function textOf(CandidateDocument $document, DocxExtractor $docx): ?string
    {
        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($document->path)) {
            return null;
        }

        $extension = mb_strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION));
        $contents = (string) $disk->get($document->path);

        $text = match ($extension) {
            'txt', 'md', 'rtf', 'csv' => $contents,
            'docx' => $docx->extract($contents),
            default => '', // pdf, jpg, png — held, not read.
        };

        return trim($text) === '' ? null : $text;
    }

    /**
     * Keep only the fields the prompt defines, and force the one conclusion
     * the model is not allowed to reach.
     *
     * @param  array<string, mixed>  $verdict
     * @return array<string, mixed>
     */
    private function sanitise(array $verdict): array
    {
        $recommendation = is_string($verdict['recommendation'] ?? null) ? $verdict['recommendation'] : 'cannot_assess';

        if (! in_array($recommendation, ['looks_consistent', 'needs_a_closer_look', 'cannot_assess'], true)) {
            $recommendation = 'cannot_assess';
        }

        $strings = static fn (mixed $v): array => is_array($v)
            ? array_values(array_filter($v, static fn ($s): bool => is_string($s) && $s !== ''))
            : [];

        return [
            'readable' => (bool) ($verdict['readable'] ?? false),
            'document_matches_kind' => isset($verdict['document_matches_kind'])
                ? (bool) $verdict['document_matches_kind']
                : null,
            'name_on_document' => is_string($verdict['name_on_document'] ?? null) ? $verdict['name_on_document'] : null,
            'name_matches_profile' => isset($verdict['name_matches_profile']) && $verdict['name_matches_profile'] !== null
                ? (bool) $verdict['name_matches_profile']
                : null,
            'employer_or_issuer' => is_string($verdict['employer_or_issuer'] ?? null) ? $verdict['employer_or_issuer'] : null,
            'period_stated' => is_string($verdict['period_stated'] ?? null) ? $verdict['period_stated'] : null,
            'observations' => $strings($verdict['observations'] ?? []),
            'concerns' => $strings($verdict['concerns'] ?? []),
            'recommendation' => $recommendation,
            'summary' => is_string($verdict['summary'] ?? null) ? mb_substr($verdict['summary'], 0, 500) : '',
            // Stated in the stored record, not just in the UI: whatever the
            // model wrote, this was never a confirmation of authenticity.
            'is_advice_only' => true,
        ];
    }
}

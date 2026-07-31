<?php

declare(strict_types=1);

namespace App\Support\Verification\Providers;

use App\Enums\VerificationKind;
use App\Jobs\AssessCandidateDocument;
use App\Models\CandidateDocument;
use App\Models\User;
use App\Support\Verification\VerificationOutcome;
use App\Support\Verification\VerificationProvider;

/**
 * The documents check, with each uploaded file read by the AI first
 * (PRD-E F9).
 *
 * It always returns *pending*, and that is the design rather than a gap. The
 * model reads each document and hands the ops reviewer a per-file summary,
 * the facts it found and anything inconsistent; the reviewer settles the
 * check. A model cannot confirm a document is genuine — it never sees the
 * paper, the stamp or the signature — so letting it grant a verified badge
 * would be asserting to employers something nobody established.
 *
 * What this buys over plain manual review is real: four documents arrive as
 * four separate assessments instead of one folder someone opens cold.
 */
final class AiDocumentProvider implements VerificationProvider
{
    public function name(): string
    {
        return 'ai_documents';
    }

    public function supports(VerificationKind $kind): bool
    {
        return $kind === VerificationKind::Documents;
    }

    /**
     * @param  array<string, mixed>  $submission
     */
    public function start(User $candidate, VerificationKind $kind, array $submission): VerificationOutcome
    {
        // One job per document — the reviewer gets a verdict per file, and a
        // slow or failed assessment on one never blocks the others.
        CandidateDocument::query()
            ->where('user_id', $candidate->id)
            ->whereNull('assessed_at')
            ->orderBy('id')
            ->get()
            ->each(fn (CandidateDocument $document) => AssessCandidateDocument::dispatch($document->id));

        return VerificationOutcome::pending();
    }
}

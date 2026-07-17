<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AiPurpose;
use App\Models\InterviewTranscript;
use App\Models\RealInterviewQuestion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Support\Interviews\Anonymizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The AI parsing stage (PRD §6.6): anonymised transcript → structured question
 * records → second scrub pass → dedupe into the bank as PENDING (the review
 * queue gates what goes live). A repeat question bumps asked_count instead of
 * duplicating — that counter IS the frequency signal for analytics.
 */
final class ParseInterviewTranscript implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function __construct(public readonly int $transcriptId) {}

    public function handle(AiGateway $gateway, Anonymizer $anonymizer): void
    {
        $transcript = InterviewTranscript::query()->withoutGlobalScopes()->find($this->transcriptId);
        if ($transcript === null || $transcript->raw_text === null) {
            return;
        }

        $tenant = Tenant::query()->find($transcript->tenant_id);
        $uploader = User::query()->withoutGlobalScopes()->find($transcript->uploader_id);
        if ($tenant === null || $uploader === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($gateway, $anonymizer, $transcript, $uploader): void {
            try {
                $result = $gateway->complete($uploader, AiPurpose::TranscriptParse, 'transcript_parse', 1, [
                    'role_title' => $transcript->role_title ?? 'the target role',
                    'transcript' => mb_substr($transcript->raw_text, 0, 24000),
                ], ['max_tokens' => 3000]);

                $parsed = json_decode(trim($result->text), true);
                if (! is_array($parsed) || ! is_array($parsed['questions'] ?? null)) {
                    throw new \RuntimeException('Parser returned malformed JSON.');
                }
            } catch (Throwable $e) {
                $transcript->update([
                    'status' => InterviewTranscript::STATUS_FAILED,
                    'parse_error' => mb_substr($e->getMessage(), 0, 480),
                ]);

                return;
            }

            $stored = 0;
            foreach ($parsed['questions'] as $raw) {
                if (! is_array($raw) || trim((string) ($raw['question'] ?? '')) === '') {
                    continue;
                }

                if ($this->bank($anonymizer->scrubRecord($raw), $transcript, (string) ($parsed['outcome'] ?? 'unknown'))) {
                    $stored++;
                }
            }

            $transcript->update([
                'status' => InterviewTranscript::STATUS_PARSED,
                'outcome' => $transcript->outcome ?? (string) ($parsed['outcome'] ?? 'unknown'),
                'questions_found' => $stored,
                'parse_error' => null,
            ]);
        });
    }

    /**
     * Dedupe-insert one parsed question. Returns true when it was counted
     * (new row created or an existing row's frequency bumped).
     *
     * @param  array<string, mixed>  $q
     */
    private function bank(array $q, InterviewTranscript $transcript, string $outcome): bool
    {
        $role = $transcript->role_title ?? 'General';
        $normalized = trim((string) ($q['normalized'] ?? $q['question']));
        $fingerprint = RealInterviewQuestion::fingerprintFor($role, $normalized);

        return DB::transaction(function () use ($q, $transcript, $outcome, $role, $normalized, $fingerprint): bool {
            $existing = RealInterviewQuestion::query()
                ->where('fingerprint', $fingerprint)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->update([
                    'asked_count' => $existing->asked_count + 1,
                    'last_seen_at' => now(),
                ]);

                return true;
            }

            RealInterviewQuestion::query()->create([
                'tenant_id' => $transcript->tenant_id,
                'interview_transcript_id' => $transcript->id,
                'course_id' => $transcript->course_id,
                'role_title' => $role,
                'question' => trim((string) $q['question']),
                'normalized_question' => $normalized,
                'fingerprint' => $fingerprint,
                'topic_tags' => array_values(array_filter(array_map(
                    fn ($t) => mb_strtolower(trim((string) $t)),
                    is_array($q['topics'] ?? null) ? $q['topics'] : [],
                ))),
                'difficulty' => $q['difficulty'] ?? null,
                'round_type' => $q['round'] ?? null,
                'follow_ups' => is_array($q['follow_ups'] ?? null) ? array_values($q['follow_ups']) : null,
                'strong_answer' => $q['strong_answer'] ?? null,
                'struggle_points' => $q['struggle'] ?? null,
                'outcome' => $outcome,
                'confidence' => in_array($q['confidence'] ?? null, ['high', 'medium', 'low'], true) ? $q['confidence'] : 'medium',
                'status' => RealInterviewQuestion::STATUS_PENDING,
                'last_seen_at' => now(),
            ]);

            return true;
        });
    }
}

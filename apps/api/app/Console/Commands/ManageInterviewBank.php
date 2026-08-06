<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\RealInterviewQuestion;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * CRUD for the real-interview question bank, driven from the CRM.
 *
 * This is the bank the mock interviewer prefers: {@see AnswerMockInterview}
 * pulls up to five APPROVED questions matching the blueprint's course or role,
 * most-asked first, and tells the model to favour them. Anything not approved
 * is invisible to it, which is why an empty bank leaves the AI inventing every
 * question.
 *
 * Long text arrives via files — a strong answer can run past argv limits.
 * `list` and `save` print JSON so the CRM can parse the result.
 */
final class ManageInterviewBank extends Command
{
    protected $signature = 'interview:bank
        {action : list, save, import, delete, approve or reject}
        {--id= : question id for save, delete, approve, reject}
        {--course= : course id or slug}
        {--role= : role title, e.g. Data Engineer}
        {--question-file= : path to a UTF-8 text file holding the question}
        {--answer-file= : path to a UTF-8 text file holding a strong answer}
        {--difficulty= : easy, medium or hard}
        {--round= : screening, technical, system_design, hr or managerial}
        {--status= : pending, approved or rejected}
        {--rows-file= : path to a JSON array of rows, for import}
        {--tenant=1}';

    protected $description = 'List, add, edit or approve questions in the real-interview bank';

    public function handle(): int
    {
        $tenant = Tenant::query()->find((int) $this->option('tenant'));

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        return app(TenantContext::class)->run($tenant, function () use ($tenant): int {
            try {
                return match ($this->argument('action')) {
                    'list' => $this->listQuestions(),
                    'save' => $this->save($tenant),
                    'import' => $this->importRows($tenant),
                    'delete' => $this->deleteQuestion(),
                    'approve' => $this->setStatus(RealInterviewQuestion::STATUS_APPROVED),
                    'reject' => $this->setStatus(RealInterviewQuestion::STATUS_REJECTED),
                    default => $this->unknownAction(),
                };
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        });
    }

    private function listQuestions(): int
    {
        $rows = RealInterviewQuestion::query()
            ->with('course:id,name')
            ->orderByDesc('status')
            ->orderByDesc('asked_count')
            ->orderBy('id')
            ->get()
            ->map(fn (RealInterviewQuestion $q): array => [
                'id' => $q->id,
                'course_id' => $q->course_id,
                'course' => $q->course?->name,
                'role_title' => $q->role_title,
                'question' => $q->question,
                'strong_answer' => $q->strong_answer,
                'difficulty' => $q->difficulty,
                'round_type' => $q->round_type,
                'status' => $q->status,
                'asked_count' => $q->asked_count,
            ])
            ->all();

        $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function save(Tenant $tenant): int
    {
        $question = $this->option('id') !== null
            ? RealInterviewQuestion::query()->find((int) $this->option('id'))
            : new RealInterviewQuestion(['tenant_id' => $tenant->id]);

        if ($question === null) {
            $this->error('Question not found.');

            return self::FAILURE;
        }

        $text = $this->fileContents('question-file');

        if ($text !== null) {
            $question->question = trim($text);
            // The fingerprint is what stops the same question being stored twice
            // when transcripts are parsed; keep it in step with manual edits.
            $question->normalized_question = mb_strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? $text));
            $question->fingerprint = hash('sha256', $question->normalized_question);
        }

        if (trim((string) $question->question) === '') {
            $this->error('The question text is required.');

            return self::FAILURE;
        }

        $answer = $this->fileContents('answer-file');

        if ($answer !== null) {
            $question->strong_answer = trim($answer) !== '' ? trim($answer) : null;
        }

        if ($this->option('course') !== null) {
            $question->course_id = $this->resolveCourseId();
        }

        foreach (['role' => 'role_title', 'difficulty' => 'difficulty', 'round' => 'round_type', 'status' => 'status'] as $option => $column) {
            if ($this->option($option) !== null && trim((string) $this->option($option)) !== '') {
                $question->{$column} = (string) $this->option($option);
            }
        }

        $question->tenant_id ??= $tenant->id;
        // Both are NOT NULL with no default — a transcript-parsed question always
        // carries them, a hand-written one needs them filled in.
        $question->topic_tags ??= [];
        $question->follow_ups ??= [];
        $question->role_title = $question->role_title ?: 'General';
        // A hand-written question is trusted: the CRM is the approval surface.
        $question->status ??= RealInterviewQuestion::STATUS_APPROVED;
        $question->asked_count ??= 0;

        if ($question->status === RealInterviewQuestion::STATUS_APPROVED && $question->approved_at === null) {
            $question->approved_at = now();
        }

        $question->save();

        $this->line((string) json_encode([
            'id' => $question->id,
            'status' => $question->status,
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function setStatus(string $status): int
    {
        $question = RealInterviewQuestion::query()->find((int) $this->option('id'));

        if ($question === null) {
            $this->error('Question not found.');

            return self::FAILURE;
        }

        $question->forceFill([
            'status' => $status,
            'approved_at' => $status === RealInterviewQuestion::STATUS_APPROVED ? now() : null,
        ])->save();

        $this->line((string) json_encode(['id' => $question->id, 'status' => $status], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function deleteQuestion(): int
    {
        $question = RealInterviewQuestion::query()->find((int) $this->option('id'));

        if ($question === null) {
            $this->error('Question not found.');

            return self::FAILURE;
        }

        $question->delete();

        $this->line((string) json_encode(['deleted' => true], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * Bulk-load a spreadsheet the CRM has already parsed into rows.
     *
     * Each row: {row, question, answer?, course?, role?, difficulty?, round?, status?}.
     * Deduped on the same fingerprint the transcript parser uses, so re-uploading
     * a sheet after fixing three lines re-imports only what is genuinely new.
     */
    private function importRows(Tenant $tenant): int
    {
        $path = $this->option('rows-file');

        if ($path === null || ! is_readable((string) $path)) {
            $this->error('Rows file not readable.');

            return self::FAILURE;
        }

        $decoded = json_decode((string) file_get_contents((string) $path), true);

        if (! is_array($decoded)) {
            $this->error('Rows must be a JSON array.');

            return self::FAILURE;
        }

        $imported = 0;
        $duplicates = 0;
        $invalid = [];

        foreach ($decoded as $index => $row) {
            $line = (int) ($row['row'] ?? $index + 1);
            $text = trim((string) ($row['question'] ?? ''));

            if ($text === '') {
                $invalid[] = "Row {$line}: no question text";

                continue;
            }

            $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? $text));
            $fingerprint = hash('sha256', $normalized);

            if (RealInterviewQuestion::query()->where('fingerprint', $fingerprint)->exists()) {
                $duplicates++;

                continue;
            }

            $status = $this->allowed((string) ($row['status'] ?? ''), ['pending', 'approved', 'rejected'])
                ?? RealInterviewQuestion::STATUS_APPROVED;

            RealInterviewQuestion::query()->create([
                'tenant_id' => $tenant->id,
                'course_id' => $this->courseIdFor((string) ($row['course'] ?? '')),
                'role_title' => trim((string) ($row['role'] ?? '')) ?: 'General',
                'question' => $text,
                'normalized_question' => $normalized,
                'fingerprint' => $fingerprint,
                'strong_answer' => trim((string) ($row['answer'] ?? '')) ?: null,
                'difficulty' => $this->allowed((string) ($row['difficulty'] ?? ''), ['easy', 'medium', 'hard']),
                'round_type' => $this->allowed((string) ($row['round'] ?? ''), ['screening', 'technical', 'system_design', 'hr', 'managerial']),
                'topic_tags' => [],
                'follow_ups' => [],
                'status' => $status,
                'asked_count' => 0,
                'approved_at' => $status === RealInterviewQuestion::STATUS_APPROVED ? now() : null,
            ]);

            $imported++;
        }

        $this->line((string) json_encode([
            'imported' => $imported,
            'duplicates' => $duplicates,
            'invalid' => $invalid,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /** A spreadsheet cell only wins if it is one of the values the column accepts. */
    private function allowed(string $value, array $allowed): ?string
    {
        $clean = mb_strtolower(trim($value));

        return in_array($clean, $allowed, true) ? $clean : null;
    }

    /** Sheets carry a course name ("Data Engineering") far more often than a slug. */
    private function courseIdFor(string $reference): ?int
    {
        $reference = trim($reference);

        if ($reference === '') {
            return null;
        }

        return Course::query()
            ->where('slug', $reference)
            ->orWhere('name', $reference)
            ->value('id');
    }

    private function resolveCourseId(): ?int
    {
        $course = $this->option('course');

        if ($course === null || trim((string) $course) === '') {
            return null;
        }

        return Course::query()
            ->when(
                is_numeric($course),
                fn ($q) => $q->whereKey((int) $course),
                fn ($q) => $q->where('slug', $course),
            )
            ->value('id');
    }

    private function fileContents(string $option): ?string
    {
        $path = $this->option($option);

        if ($path === null || ! is_readable((string) $path)) {
            return null;
        }

        return (string) file_get_contents((string) $path);
    }

    private function unknownAction(): int
    {
        $this->error('Unknown action — use list, save, delete, approve or reject.');

        return self::FAILURE;
    }
}

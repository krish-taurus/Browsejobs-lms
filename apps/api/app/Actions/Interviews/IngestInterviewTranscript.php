<?php

declare(strict_types=1);

namespace App\Actions\Interviews;

use App\Jobs\ParseInterviewTranscript;
use App\Jobs\TranscribeInterviewMedia;
use App\Models\InterviewTranscript;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Interviews\Anonymizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Entry point of the transcript ingestion pipeline (PRD §6.6). Source-agnostic:
 * pasted text / Parakeet exports / debrief notes parse immediately; txt/docx
 * files are text-extracted; audio/video queues transcription; zip fans out
 * per entry. Originals are stored ENCRYPTED; the working copy is machine-
 * scrubbed before any AI sees it. Consent is already validated by the request
 * — here it is stamped and audit-logged.
 */
final readonly class IngestInterviewTranscript
{
    private const TEXT_EXTENSIONS = ['txt', 'md', 'vtt', 'json', 'srt'];

    private const MEDIA_EXTENSIONS = ['mp3', 'mp4', 'wav', 'm4a', 'webm', 'mov', 'ogg'];

    private const MAX_ZIP_ENTRIES = 50;

    public function __construct(
        private Anonymizer $anonymizer,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{source: string, text?: string|null, course_id?: int|null, role_title?: string|null}  $data
     */
    public function handle(User $uploader, array $data, ?UploadedFile $file = null): InterviewTranscript
    {
        $base = [
            'tenant_id' => $uploader->tenant_id,
            'uploader_id' => $uploader->id,
            'course_id' => $data['course_id'] ?? null,
            'role_title' => $data['role_title'] ?? null,
            'source' => $data['source'],
            'consent_confirmed_at' => now(),
        ];

        $transcript = $file !== null
            ? $this->fromFile($base, $file)
            : $this->fromText($base, (string) ($data['text'] ?? ''));

        $this->audit->log('interview_transcript.uploaded', $transcript, [
            'source' => $transcript->source,
            'consent' => true,
            'original_name' => $transcript->original_name,
        ], $uploader);

        return $transcript;
    }

    /**
     * @param  array<string, mixed>  $base
     */
    private function fromText(array $base, string $text): InterviewTranscript
    {
        if (trim($text) === '') {
            throw ValidationException::withMessages(['text' => 'The transcript text is empty.']);
        }

        $transcript = InterviewTranscript::query()->create([
            ...$base,
            'raw_text' => $this->anonymizer->scrub($text),
            'status' => InterviewTranscript::STATUS_PARSING,
        ]);

        ParseInterviewTranscript::dispatch($transcript->id);

        return $transcript;
    }

    /**
     * @param  array<string, mixed>  $base
     */
    private function fromFile(array $base, UploadedFile $file): InterviewTranscript
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $contents = (string) $file->get();

        $base['original_name'] = $file->getClientOriginalName();
        $base['encrypted_path'] = $this->storeEncrypted($contents, $extension);

        if (in_array($extension, self::TEXT_EXTENSIONS, true)) {
            return $this->fromText($base, $contents);
        }

        if ($extension === 'docx') {
            return $this->fromText($base, $this->extractDocxText($contents));
        }

        if ($extension === 'zip') {
            return $this->fromZip($base, $contents);
        }

        if (in_array($extension, self::MEDIA_EXTENSIONS, true)) {
            $transcript = InterviewTranscript::query()->create([
                ...$base,
                'status' => InterviewTranscript::STATUS_TRANSCRIBING,
            ]);

            TranscribeInterviewMedia::dispatch($transcript->id);

            return $transcript;
        }

        // PDFs and anything else we cannot honestly extract yet: keep the
        // encrypted original, fail loudly with the operator's next step.
        return InterviewTranscript::query()->create([
            ...$base,
            'status' => InterviewTranscript::STATUS_FAILED,
            'parse_error' => "No text extractor for .{$extension} yet — paste the transcript as text; the original is stored encrypted.",
        ]);
    }

    /**
     * Fan a zip of .txt transcripts out into one child per entry; the zip row
     * itself records the batch.
     *
     * @param  array<string, mixed>  $base
     */
    private function fromZip(array $base, string $contents): InterviewTranscript
    {
        $parent = InterviewTranscript::query()->create([
            ...$base,
            'status' => InterviewTranscript::STATUS_PARSED,
            'parse_error' => null,
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'bjl-zip');
        file_put_contents($tmp, $contents);

        $zip = new ZipArchive;
        $children = 0;

        try {
            if ($zip->open($tmp) !== true) {
                $parent->update([
                    'status' => InterviewTranscript::STATUS_FAILED,
                    'parse_error' => 'Could not open the zip archive.',
                ]);

                return $parent;
            }

            for ($i = 0; $i < min($zip->numFiles, self::MAX_ZIP_ENTRIES); $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::TEXT_EXTENSIONS, true)) {
                    continue;
                }

                $entry = (string) $zip->getFromIndex($i);
                if (trim($entry) === '') {
                    continue;
                }

                $this->fromText([
                    ...$base,
                    'original_name' => basename($name),
                    'encrypted_path' => null, // the batch original lives on the parent row
                ], $entry);
                $children++;
            }

            $zip->close();
        } finally {
            @unlink($tmp);
        }

        $parent->update([
            'parse_error' => $children === 0 ? 'The zip contained no readable .txt transcripts.' : null,
            'status' => $children === 0 ? InterviewTranscript::STATUS_FAILED : InterviewTranscript::STATUS_PARSED,
            'questions_found' => 0,
        ]);

        return $parent;
    }

    /** Word documents are zip archives; the body text lives in word/document.xml. */
    private function extractDocxText(string $contents): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bjl-docx');
        file_put_contents($tmp, $contents);

        try {
            $zip = new ZipArchive;
            if ($zip->open($tmp) !== true) {
                return '';
            }

            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();

            // Paragraph/break tags become newlines so Q/A structure survives.
            $xml = (string) preg_replace('/<w:(p|br)[^>]*>/', "\n", $xml);

            return trim(html_entity_decode(strip_tags($xml)));
        } finally {
            @unlink($tmp);
        }
    }

    private function storeEncrypted(string $contents, string $extension): string
    {
        $path = 'interviews/originals/'.Str::uuid().'.'.$extension.'.enc';
        Storage::disk('s3')->put($path, Crypt::encryptString($contents));

        return $path;
    }
}

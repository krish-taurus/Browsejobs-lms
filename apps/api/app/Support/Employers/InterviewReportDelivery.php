<?php

declare(strict_types=1);

namespace App\Support\Employers;

use App\Actions\Employers\BuildInterviewReport;
use App\Models\EmployerInterviewReport;
use App\Models\EmployerJob;
use App\Models\EmployerWhatsappLink;
use App\Models\EmployerWhatsappMessage;
use App\Models\MockInterview;
use App\Support\Mocks\RecordingLink;
use App\Support\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gets an interview report (and the CV) onto the client's WhatsApp.
 *
 * WhatsApp only allows files inside the 24-hour window the client's own
 * message opened. Inside it, the PDFs go straight away. Outside it, the
 * report waits (pending) and the client gets one approved update line —
 * "reports are ready, reply to get them" — and the next message they send
 * delivers everything waiting (HiringChatAgent calls deliverPending first).
 * The files go as short-lived signed links; the storage stays private.
 */
final readonly class InterviewReportDelivery
{
    private const LINK_HOURS = 12;

    /** Most waiting reports sent in one go when the client writes back. */
    private const BATCH = 10;

    public function __construct(
        private WhatsAppClient $whatsapp,
        private InterviewTemplates $templates,
    ) {}

    public function deliver(EmployerInterviewReport $report): void
    {
        $job = EmployerJob::query()->find($report->employer_job_id);
        $link = $job === null ? null : (($job->whatsapp_link_id !== null ? EmployerWhatsappLink::query()->find($job->whatsapp_link_id) : null)
            ?? EmployerWhatsappLink::query()->where('employer_workspace_id', $job->employer_workspace_id)->latest('last_inbound_at')->first());

        if ($job === null || $link === null) {
            $report->update(['status' => EmployerInterviewReport::NO_CHAT]);

            return; // Nobody at the company is on WhatsApp: it lives on the candidate page.
        }

        $report->update(['employer_whatsapp_link_id' => $link->id]);

        if (self::windowOpen($link)) {
            $this->send($report, $link);

            return;
        }

        // Ask once: a second report waiting behind the first needs no second nudge.
        $alreadyWaiting = EmployerInterviewReport::query()
            ->where('employer_whatsapp_link_id', $link->id)
            ->where('status', EmployerInterviewReport::PENDING)
            ->whereKeyNot($report->id)
            ->exists();
        if ($alreadyWaiting || ! $this->templates->approved(InterviewTemplates::UPDATE)) {
            return;
        }

        $line = 'An interview report with the CV is ready ('.Str::limit(ltrim($report->caption, '📄 '), 200).'). Reply to this message to get the PDF here.';
        $this->log($link, "📣 *{$job->title}* · {$line}", function () use ($link, $job, $line): string {
            $contact = trim(explode(' ', trim((string) $link->user?->name))[0] ?? '') ?: 'there';

            return $this->whatsapp->sendMessage($link->phone, '', InterviewTemplates::UPDATE, [Str::limit($contact, 30, ''), (string) $job->title, $line]);
        });
    }

    /** The client has just written: everything waiting for them goes now. */
    public function deliverPending(EmployerWhatsappLink $link): int
    {
        $waiting = EmployerInterviewReport::query()
            ->where('employer_whatsapp_link_id', $link->id)
            ->where('status', EmployerInterviewReport::PENDING)
            ->orderBy('id')
            ->limit(self::BATCH)
            ->get();

        foreach ($waiting as $report) {
            $this->send($report, $link);
        }

        return $waiting->count();
    }

    /**
     * The client asks for a report again ("send Kunal's report"): the PDF goes now
     * (their message just opened the 24-hour window), then the recording link.
     */
    public function resend(EmployerInterviewReport $report, EmployerWhatsappLink $link, bool $withRecording = true): void
    {
        $this->send($report, $link);

        $mock = $report->interview?->mock_interview_id !== null ? MockInterview::query()->withoutGlobalScopes()->find($report->interview->mock_interview_id) : null;
        if ($withRecording && $mock !== null) {
            $name = (string) ($report->interview?->application?->candidate()->withoutGlobalScopes()->value('name') ?? 'Candidate');
            $this->log($link, "🎥 Recording · {$name}\n".RecordingLink::for($mock), fn (): string => $this->whatsapp->sendMessage($link->phone, "🎥 Full interview recording · {$name}\n".RecordingLink::for($mock)."\n\nThe link works for ".RecordingLink::DAYS.' days and needs no login.'));
        }
    }

    private function send(EmployerInterviewReport $report, EmployerWhatsappLink $link): void
    {
        $disk = Storage::disk(BuildInterviewReport::DISK);
        $name = (string) ($report->interview?->application?->candidate()->withoutGlobalScopes()->value('name') ?? 'Candidate');
        $file = Str::slug($name, ' ') ?: 'candidate';

        try {
            $this->log($link, $report->caption, fn (): string => $this->whatsapp->sendDocument(
                $link->phone,
                $disk->temporaryUrl($report->pdf_path, now()->addHours(self::LINK_HOURS)),
                "{$file} - interview report.pdf",
                $report->caption,
            ), rethrow: true);

            // The CV goes with a candidate's first report; later rounds don't repeat it.
            $applicationId = $report->interview?->employer_job_application_id;
            $cvSentBefore = EmployerInterviewReport::query()
                ->where('status', EmployerInterviewReport::SENT)
                ->whereKeyNot($report->id)
                ->whereHas('interview', fn ($q) => $q->where('employer_job_application_id', $applicationId))
                ->exists();

            if ($report->cv_path !== null && ! $cvSentBefore) {
                $this->log($link, "📎 CV · {$name}", fn (): string => $this->whatsapp->sendDocument(
                    $link->phone,
                    $disk->temporaryUrl($report->cv_path, now()->addHours(self::LINK_HOURS)),
                    "{$file} - CV.pdf",
                    "CV · {$name}",
                ));
            }

            $report->update(['status' => EmployerInterviewReport::SENT, 'sent_at' => now()]);
        } catch (Throwable $e) {
            report($e);
            $report->update(['status' => EmployerInterviewReport::FAILED]);
        }
    }

    /** Every outbound message is kept in the chat log, failed or not. */
    private function log(EmployerWhatsappLink $link, string $body, callable $send, bool $rethrow = false): void
    {
        $message = EmployerWhatsappMessage::query()->create([
            'tenant_id' => $link->tenant_id,
            'employer_whatsapp_link_id' => $link->id,
            'phone' => $link->phone,
            'direction' => 'out',
            'body' => $body,
            'status' => 'sent',
        ]);

        try {
            $message->update(['wamid' => $send()]);
        } catch (Throwable $e) {
            $message->update(['status' => 'failed']);
            if ($rethrow) {
                throw $e;
            }
            report($e);
        }
    }

    public static function windowOpen(EmployerWhatsappLink $link): bool
    {
        return $link->last_inbound_at !== null && $link->last_inbound_at->gt(now()->subHours(23));
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\MessageMail;
use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Staff alert for a new enquiry, on the default queue.
 *
 * Production runs `queue:work redis` (no Horizon). The enquiry row is already
 * saved. A mail failure is logged and stored on the row; it is not thrown,
 * so a dead SMTP server cannot fail the HTTP request or the queue job.
 * Nothing is sent to the person who submitted.
 */
final class NotifyEnquiry implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $enquiryId) {}

    public function handle(): void
    {
        $enquiry = Enquiry::query()->withoutGlobalScopes()->find($this->enquiryId);

        if ($enquiry === null || $enquiry->notified_at !== null) {
            return;
        }

        $to = match ($enquiry->type) {
            Enquiry::TYPE_EMPLOYER => (string) config('enquiry.notify_employer'),
            Enquiry::TYPE_COUNSELLING => (string) config('enquiry.notify_counselling'),
            default => (string) config('enquiry.notify_course'),
        };

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->recordFailure($enquiry, 'Notification address is not a valid email.');

            return;
        }

        try {
            Mail::to($to)->send(new MessageMail(
                $this->subject($enquiry),
                $this->body($enquiry),
            ));
        } catch (Throwable $e) {
            $this->recordFailure($enquiry, $e->getMessage());

            return;
        }

        $enquiry->forceFill([
            'notified_at' => now(),
            'notify_error' => null,
        ])->save();
    }

    private function recordFailure(Enquiry $enquiry, string $message): void
    {
        $message = trim($message) !== '' ? trim($message) : 'Notification failed.';
        $stored = mb_substr($message, 0, 500);

        Log::warning('Enquiry notification failed', [
            'enquiry_id' => $enquiry->id,
            'message' => $stored,
        ]);

        $enquiry->forceFill(['notify_error' => $stored])->save();
    }

    private function subject(Enquiry $enquiry): string
    {
        if ($enquiry->type === Enquiry::TYPE_EMPLOYER) {
            return 'New employer enquiry — '.($enquiry->company ?: $enquiry->name);
        }

        if ($enquiry->type === Enquiry::TYPE_COUNSELLING) {
            return 'New counselling enquiry — '.$enquiry->name;
        }

        return 'New course enquiry — '.($enquiry->course_slug ?: $enquiry->name);
    }

    private function body(Enquiry $enquiry): string
    {
        $lines = [
            'A new '.$enquiry->type.' enquiry arrived.',
            '',
            'Name: '.$enquiry->name,
            'Email: '.$enquiry->email,
            'Phone: '.$enquiry->phone,
        ];

        if ($enquiry->type === Enquiry::TYPE_EMPLOYER) {
            $lines[] = 'Company: '.(string) $enquiry->company;
            $lines[] = 'Company size: '.$enquiry->label('COMPANY_SIZES', $enquiry->company_size);
            $lines[] = 'Roles: '.(string) $enquiry->roles;
            $lines[] = 'Timeline: '.$enquiry->label('TIMELINES', $enquiry->timeline);
        } elseif ($enquiry->type === Enquiry::TYPE_COUNSELLING) {
            $lines[] = 'Request: free counselling session';
            $lines[] = 'Preferred time: '.$enquiry->label('PREFERRED_TIMES', $enquiry->preferred_time);
        } else {
            $lines[] = 'Course: '.(string) $enquiry->course_slug;
            $lines[] = 'Status: '.$enquiry->label('LEARNER_STATUSES', $enquiry->learner_status);
            $lines[] = 'Preferred time: '.$enquiry->label('PREFERRED_TIMES', $enquiry->preferred_time);
        }

        $lines[] = 'City: '.(string) $enquiry->city;

        if ($enquiry->message) {
            $lines[] = '';
            $lines[] = 'Message:';
            $lines[] = $enquiry->message;
        }

        $lines[] = '';
        $lines[] = 'Page: '.(string) $enquiry->landing_page;
        $lines[] = 'Referrer: '.(string) $enquiry->referrer;
        $lines[] = 'UTM: '.trim(implode(' / ', array_filter([
            $enquiry->utm_source,
            $enquiry->utm_medium,
            $enquiry->utm_campaign,
        ])));

        return implode("\n", $lines);
    }
}

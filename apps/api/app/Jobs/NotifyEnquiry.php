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
use Illuminate\Support\Facades\Mail;

/**
 * Tells the team about a new enquiry. Idempotent via notified_at.
 * Nothing is sent to the person who submitted.
 */
final class NotifyEnquiry implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $enquiryId) {}

    public function handle(): void
    {
        $enquiry = Enquiry::query()->withoutGlobalScopes()->find($this->enquiryId);

        if ($enquiry === null || $enquiry->notified_at !== null) {
            return;
        }

        $to = $enquiry->type === Enquiry::TYPE_EMPLOYER
            ? (string) config('enquiry.notify_employer')
            : (string) config('enquiry.notify_course');

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        Mail::to($to)->send(new MessageMail(
            $this->subject($enquiry),
            $this->body($enquiry),
        ));

        $enquiry->forceFill(['notified_at' => now()])->save();
    }

    private function subject(Enquiry $enquiry): string
    {
        if ($enquiry->type === Enquiry::TYPE_EMPLOYER) {
            return 'New employer enquiry — '.($enquiry->company ?: $enquiry->name);
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

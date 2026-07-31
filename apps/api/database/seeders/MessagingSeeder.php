<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MessageCategory;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\InAppNotification;
use App\Models\Message;
use App\Models\MessagePreference;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Seeds the journey-subset message templates, default channel preferences, and a
 * few demo messages/notifications so the admin log + student inbox are demo-able.
 * Idempotent.
 */
class MessagingSeeder extends Seeder
{
    /** @var list<array{key: string, channel: string, category: string, subject: string|null, body: string}> */
    private const TEMPLATES = [
        ['key' => 'otp', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Your BrowseJobs code is {{code}}. It expires in 10 minutes.'],
        ['key' => 'otp', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Your BrowseJobs code', 'body' => 'Your BrowseJobs code is {{code}}. It expires in 10 minutes.'],
        ['key' => 'lead_welcome', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, thanks for your interest in BrowseJobs! Your free masterclass link is ready — join here: {{link}}'],
        ['key' => 'lead_welcome', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Your free BrowseJobs masterclass', 'body' => 'Hi {{name}}, thanks for your interest in BrowseJobs. Join your free masterclass here: {{link}}. We look forward to seeing you there.'],
        ['key' => 'class_reminder', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your class "{{title}}" starts in {{window}}. Join here: {{link}}'],
        ['key' => 'class_cancelled', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your class "{{title}}" is cancelled. {{reason}}'],
        ['key' => 'class_rescheduled', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your class "{{title}}" was rescheduled. {{reason}}'],
        ['key' => 'class_wrapup', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your flashcards for "{{title}}" are ready. A few minutes of recall now beats an hour of re-reading later: {{link}}'],
        ['key' => 'class_wrapup', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Review your class — {{title}}', 'body' => 'Hi {{name}}, your flashcards for "{{title}}" are ready. Spend a few minutes on recall while it is fresh: {{link}}'],
        ['key' => 'class_cancelled_trainer', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your class "{{title}}" for {{batch}} is cancelled and the batch has been notified. {{reason}}'],
        ['key' => 'class_rescheduled_trainer', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your class "{{title}}" for {{batch}} moved to {{starts}} and the batch has been notified. {{reason}}'],
        ['key' => 'fee_reminder', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, INR {{amount}} is due. Pay in one tap: {{link}}'],
        ['key' => 'fee_blocked', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your access is paused ({{level}}) until your fees are cleared.'],
        ['key' => 'fee_unblocked', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your access is restored. Welcome back!'],
        ['key' => 'payment_link', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => "Hi {{name}}, 👋\n\nYour *BrowseJobs course fee EMI* is due:\n\n💰 *Amount:* ₹{{amount}}\n📄 *Instalment:* {{seq}} of {{count}}\n📅 *Due by:* {{due}}\n\n👉 Pay securely in one tap:\n{{link}}\n\n⚠️ If it stays unpaid, access to live classes and recordings is paused until the payment is cleared.\n\nNeed help? Just reply here.\n— Team BrowseJobs"],
        ['key' => 'payment_link', 'channel' => 'email', 'category' => 'utility', 'subject' => 'EMI payment of ₹{{amount}} due {{due}} — BrowseJobs', 'body' => "Hi {{name}}, your BrowseJobs course fee EMI is due.\n\nAmount: ₹{{amount}}\nInstalment: {{seq}} of {{count}}\nDue by: {{due}}\n\nPay securely in one tap: {{link}}\n\nIf it stays unpaid, access to live classes and recordings is paused until the payment is cleared. Need help? Just reply to this email."],
        ['key' => 'batch_welcome', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Welcome aboard, {{name}}! You are enrolled in {{batch}} (starts {{starts}}). Open your dashboard to get started: {{link}}'],
        ['key' => 'batch_welcome', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Welcome to {{batch}}', 'body' => 'Welcome aboard, {{name}}! You are enrolled in {{batch}}, starting {{starts}}. Open your dashboard to get started: {{link}}'],
        ['key' => 'batch_new_enrollee', 'channel' => 'inapp', 'category' => 'utility', 'subject' => 'New enrollment', 'body' => '{{student}} just enrolled in {{batch}}.'],
        ['key' => 'bootcamp_invite', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your free 7-day BrowseJobs bootcamp is ready to claim — reserve your spot here: {{link}}'],
        ['key' => 'bootcamp_invite', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Claim your free 7-day bootcamp', 'body' => 'Hi {{name}}, your free 7-day BrowseJobs bootcamp is ready. Reserve your spot here: {{link}}. It is completely free — a hands-on head start before you decide anything.'],
        ['key' => 'conversion_nudge', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, secure your seat in {{batch}} — {{seats}} left, starts {{starts}}. Complete payment: {{link}}'],
        ['key' => 'review_request', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, how has your BrowseJobs journey been so far? Leave us a quick review here: {{link}}. Follow us on Instagram & YouTube and post your review on Google, and we will send a thank-you voucher.'],
        ['key' => 'voucher_issued', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Thanks {{name}}! Your voucher {{code}} is ready — we will pre-apply it to your registration link. Valid until {{expires}}.'],
        ['key' => 'ticket_created_ack', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, we received your request ({{ref}}) about "{{subject}}". Our team is on it — track it here: {{link}}'],
        ['key' => 'ticket_assigned', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, ticket {{ref}} ("{{subject}}") is assigned to you. Open the workspace: {{link}}'],
        ['key' => 'ticket_reply', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, there is a new reply on ticket {{ref}}. View it here: {{link}}'],
        ['key' => 'ticket_resolved', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, ticket {{ref}} is marked resolved. If it helped, please rate your experience: {{link}}'],
        ['key' => 'ticket_sla_warning', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Reminder: ticket {{ref}} ("{{subject}}") is approaching its first-response SLA. {{link}}'],
        ['key' => 'module_report', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => '{{name}}, your {{module}} report is ready. Score {{total}}/100 · attendance {{attendance}}% · {{mocks_done}} mocks (avg {{mock_score}}) · assignments {{assignments}}. Full report: {{link}}'],
        ['key' => 'module_report', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Your {{module}} performance report', 'body' => 'Hi {{name}}, you completed {{module}}. Your score: {{total}}/100 — attendance {{attendance}}%, {{mocks_done}} mocks (avg {{mock_score}}), assignments {{assignments}}. See the full report: {{link}}'],
        ['key' => 'mcq_dispatch', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Nice work {{name}}! You finished {{module}}. Take the quick quiz to lock in your progress: {{link}}'],
        ['key' => 'mcq_dispatch', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Your {{module}} quiz is ready', 'body' => 'Hi {{name}}, you finished {{module}}. Take the quiz to update your progress: {{link}}'],
        ['key' => 'mcq_reminder', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your {{module}} quiz is still waiting — it only takes a few minutes: {{link}}'],
        ['key' => 'grade_ready', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your grade for "{{assignment}}" is ready: {{score}}/{{max}}. See the feedback: {{link}}'],
        ['key' => 'grade_ready', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Your grade for {{assignment}}', 'body' => 'Hi {{name}}, your grade for "{{assignment}}" is {{score}}/{{max}}. View the feedback: {{link}}'],
        ['key' => 'certificate_ready', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Congratulations {{name}}! Your {{course}} certificate is ready. View and verify it here: {{link}}'],
        ['key' => 'certificate_ready', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Your {{course}} certificate', 'body' => 'Congratulations {{name}}! You completed {{course}}. View and verify your certificate: {{link}}'],
        ['key' => 'weekly_report', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your weekly progress report is ready. Read it in one tap: {{link}}'],
        ['key' => 'weekly_report', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Your weekly progress report', 'body' => 'Hi {{name}}, your weekly progress report is ready. Read it here: {{link}}'],
        ['key' => 'trainer_brief', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, pre-class brief for {{batch}}: {{body}}'],
        ['key' => 'batch_allocation', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, you have been allocated to {{batch}} — {{detail}}. You will be notified of any schedule changes.'],
        ['key' => 'batch_allocation', 'channel' => 'email', 'category' => 'utility', 'subject' => 'You have been allocated to a batch', 'body' => 'Hi {{name}}, you have been allocated to {{batch}} — {{detail}}. You will be notified of any schedule changes to this batch.'],
        ['key' => 'counselor_digest', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your daily risk digest: {{body}}'],
        ['key' => 'mock_nudge', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Nice work {{name}} — you just finished {{topic}}. Lock it in with a 10-minute practice interview: {{link}}'],
        ['key' => 'mentor_booked', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your session with {{with}} is confirmed for {{when}}. Details & calendar invite: {{link}}'],
        ['key' => 'mentor_booked', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Session confirmed — {{when}}', 'body' => 'Hi {{name}}, your session with {{with}} is confirmed for {{when}}. Details and the calendar invite: {{link}}'],
        ['key' => 'mentor_reminder', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Reminder {{name}}: your session with {{with}} is at {{when}}. Details: {{link}}'],
        ['key' => 'mentor_cancelled', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your session with {{with}} on {{when}} was cancelled. Any used credit is back in your wallet.'],
        ['key' => 'mentor_rescheduled', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your session with {{with}} moved to {{when}}. Details: {{link}}'],
        ['key' => 'mock_nudge', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Practice interview on {{topic}}', 'body' => 'Hi {{name}}, you just finished {{topic}}. A quick practice interview locks it in while it\'s fresh: {{link}}'],
        // P3.7 weekly Market Pulse — MARKETING category: opt-in only, quiet hours + caps enforced.
        ['key' => 'market_pulse_weekly', 'channel' => 'whatsapp', 'category' => 'marketing', 'subject' => null, 'body' => "Hi {{name}}, this week's Market Pulse from BrowseJobs:\n{{body}}"],
        ['key' => 'market_pulse_weekly', 'channel' => 'email', 'category' => 'marketing', 'subject' => 'Your weekly Market Pulse', 'body' => "Hi {{name}},\n\nThis week's IT job-market pulse:\n\n{{body}}"],
        // P4.7c alumni 6/12-month check-in — service touch about the student's own placement.
        ['key' => 'alumni.checkin', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, it has been {{months}} months since you were placed — how is it going? A 2-minute update in your dashboard (under Placement) helps us support the next batch and opens referral rewards.'],
        ['key' => 'alumni.checkin', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Your {{months}}-month check-in', 'body' => 'Hi {{name}}, it has been {{months}} months since you were placed. A quick update in your dashboard (under Placement) helps us support the next batch and opens referral rewards.'],
        // P4.8b "Jobs for You" — daily match nudge, a core placement-service notification.
        ['key' => 'jobs.nudge', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, {{count}} new {{role}} role(s) match your profile today. See them under Jobs for You in your dashboard.'],
        ['key' => 'jobs.nudge', 'channel' => 'email', 'category' => 'utility', 'subject' => '{{count}} new roles match you today', 'body' => 'Hi {{name}}, {{count}} new {{role}} role(s) match your profile today. Open Jobs for You in your dashboard to review and apply.'],
        // P4.8c application follow-up — service reminder about the student's own application.
        ['key' => 'application.followup', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, you applied for {{role}} about 5 days ago. A short follow-up to the recruiter can make a real difference — worth a nudge today?'],
        // Daily Market Brief teaser — MARKETING: the headline is the hook, /brief is the destination.
        ['key' => 'daily_brief', 'channel' => 'whatsapp', 'category' => 'marketing', 'subject' => null, 'body' => "{{headline}}\n\nHi {{name}} — today's market brief is live: who announced hiring, who cut, and what it means for your prep. Read it here: {{link}}"],
        ['key' => 'daily_brief', 'channel' => 'email', 'category' => 'marketing', 'subject' => '{{headline}}', 'body' => "Hi {{name}},\n\nToday's market brief is live: who announced hiring, who cut, and what it means for your preparation.\n\nRead it: {{link}}"],
        ['key' => 'application.followup', 'channel' => 'email', 'category' => 'utility', 'subject' => 'Following up on your {{role}} application', 'body' => 'Hi {{name}}, you applied for {{role}} about 5 days ago. A short, polite follow-up to the recruiter often helps — it might be worth sending one today.'],
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, fn () => $this->seedTenant($tenant));
    }

    private function seedTenant(Tenant $tenant): void
    {
        foreach (self::TEMPLATES as $t) {
            MessageTemplate::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => $t['key'], 'channel' => $t['channel']],
                [
                    'category' => $t['category'],
                    'subject' => $t['subject'],
                    'body' => $t['body'],
                    'locale' => 'en',
                    'active' => true,
                ],
            );
        }

        $student = User::query()->where('tenant_id', $tenant->id)->where('user_type', 'student')->first();
        if ($student === null) {
            return;
        }

        MessagePreference::query()->updateOrCreate(
            ['user_id' => $student->id],
            ['tenant_id' => $tenant->id, 'preferred_channel' => MessageChannel::WhatsApp->value, 'marketing_opt_in' => false],
        );

        if (Message::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        Message::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => $student->id,
            'direction' => MessageDirection::Out->value, 'channel' => MessageChannel::WhatsApp->value,
            'template_key' => 'fee_reminder', 'category' => MessageCategory::Utility->value,
            'recipient' => $student->phone, 'body' => 'Hi, INR 10,000.00 is due. Pay in one tap.',
            'status' => MessageStatus::Delivered->value, 'sent_at' => now()->subHours(3), 'delivered_at' => now()->subHours(3),
        ]);

        InAppNotification::query()->create([
            'tenant_id' => $tenant->id, 'user_id' => $student->id,
            'title' => 'Fee reminder', 'body' => 'Your next instalment is due soon.', 'url' => '/dashboard',
        ]);
    }
}

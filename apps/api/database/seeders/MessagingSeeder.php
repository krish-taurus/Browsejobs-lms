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
        ['key' => 'lead_welcome', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, thanks for your interest in BrowseJobs. We will be in touch about your free masterclass.'],
        ['key' => 'class_reminder', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your class "{{title}}" starts in {{window}}. Join here: {{link}}'],
        ['key' => 'class_cancelled', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your class "{{title}}" is cancelled. {{reason}}'],
        ['key' => 'class_rescheduled', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your class "{{title}}" was rescheduled. {{reason}}'],
        ['key' => 'fee_reminder', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, INR {{amount}} is due. Pay in one tap: {{link}}'],
        ['key' => 'fee_blocked', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your access is paused ({{level}}) until your fees are cleared.'],
        ['key' => 'fee_unblocked', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, your access is restored. Welcome back!'],
        ['key' => 'payment_link', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, complete your registration here: {{link}}'],
        ['key' => 'conversion_nudge', 'channel' => 'whatsapp', 'category' => 'utility', 'subject' => null, 'body' => 'Hi {{name}}, secure your seat in {{batch}} — {{seats}} left, starts {{starts}}. Complete payment: {{link}}'],
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

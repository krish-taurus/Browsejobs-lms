<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Actions\Crm\RecordTimelineEvent;
use App\Enums\MessageCategory;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Jobs\SendEmailMessage;
use App\Jobs\SendWhatsAppMessage;
use App\Models\ContactTimelineEvent;
use App\Models\InAppNotification;
use App\Models\Lead;
use App\Models\Message;
use App\Models\MessagePreference;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use App\Support\MagicLink\MagicLinkService;
use Illuminate\Support\Carbon;

/**
 * The single outbound gateway (PRD §6.9). Resolves a template + channel, applies
 * the utility/marketing guards (opt-out, quiet hours, frequency cap — utility
 * bypasses all three), renders {{vars}} + a one-tap {{link}}, runs the
 * banned-phrase linter, logs a `messages` row, dispatches the channel job, and
 * records the touch on the lead's CRM timeline. Every notifier routes here.
 */
final class Messenger
{
    public function __construct(
        private readonly BannedPhraseLinter $linter,
        private readonly MagicLinkService $links,
        private readonly RecordTimelineEvent $timeline,
    ) {}

    /**
     * @param  array<string, string>  $vars
     * @param  array{category?: MessageCategory, transactional?: bool, channel?: MessageChannel, lead?: Lead, magic?: array{action: string, payload?: array<string, mixed>, ttl?: int}}  $opts
     */
    public function send(User $to, string $key, array $vars = [], array $opts = []): ?Message
    {
        $desired = $opts['channel'] ?? $this->preferredChannel($to);
        $template = $this->resolveTemplate($to->tenant_id, $key, $desired);
        if ($template === null) {
            return null;
        }

        $channel = $template->channel;
        $category = $opts['category'] ?? $template->category;
        $transactional = $opts['transactional'] ?? ($category === MessageCategory::Utility);

        $recipient = match ($channel) {
            MessageChannel::WhatsApp => $to->phone,
            MessageChannel::Email => $to->email,
            MessageChannel::InApp => null,
        };

        $lead = $opts['lead'] ?? null;

        // Guards (marketing / non-transactional only).
        if (! $transactional) {
            if ($suppressed = $this->guard($to, $category)) {
                return $this->log($to, $template, $channel, $category, $recipient, null, null, MessageStatus::Suppressed, $suppressed, $lead, null);
            }
        }

        $link = null;
        if (isset($opts['magic'])) {
            $link = $this->links->create(
                $to->tenant, $opts['magic']['action'], $to,
                $opts['magic']['payload'] ?? [], $opts['magic']['ttl'] ?? null,
            );
            $vars['link'] = $link;
        }

        $body = $this->render($template->body, $vars);
        $subject = $template->subject !== null ? $this->render($template->subject, $vars) : null;

        if (! $this->linter->passes($body.' '.($subject ?? ''))) {
            return $this->log($to, $template, $channel, $category, $recipient, $subject, $body, MessageStatus::Blocked, 'banned_phrase', $lead, $link);
        }

        $message = $this->log($to, $template, $channel, $category, $recipient, $subject, $body, MessageStatus::Queued, null, $lead, $link, $vars);

        $this->deliver($message, $to);
        $this->recordTimeline($message, $to, $lead, $body);

        // Reflects the sync-driver result (Sent) in the returned model.
        return $message->refresh();
    }

    /**
     * Send to a bare identifier (no User) — OTP during login/register, or a lead
     * confirmation. Always transactional; no preferences/guards. Records on the
     * lead timeline when a Lead is supplied.
     *
     * @param  array<string, string>  $vars
     */
    public function sendRaw(int $tenantId, MessageChannel $channel, string $to, string $key, array $vars = [], ?Lead $lead = null): ?Message
    {
        $template = $this->resolveTemplate($tenantId, $key, $channel);
        if ($template === null) {
            return null;
        }

        $body = $this->render($template->body, $vars);
        if (! $this->linter->passes($body)) {
            return null;
        }

        $message = Message::query()->create([
            'tenant_id' => $tenantId,
            'lead_id' => $lead?->id,
            'direction' => MessageDirection::Out->value,
            'channel' => $channel->value,
            'template_key' => $key,
            'category' => MessageCategory::Utility->value,
            'recipient' => $to,
            'body' => $body,
            'status' => MessageStatus::Queued->value,
            'meta' => $channel === MessageChannel::WhatsApp ? $this->whatsAppMeta($key, $template->name, $vars) : null,
        ]);

        $this->deliver($message, null);

        if ($lead !== null) {
            $this->timeline->handle($lead, ContactTimelineEvent::TYPE_MESSAGE_OUT, $body, ['channel' => $channel->value, 'template' => $key]);
        }

        return $message;
    }

    /**
     * Meta payload hints for a templated WhatsApp send: which approved template
     * carries this key, its ordered {{n}} values, and whether it is an
     * AUTHENTICATION template (copy-code button). A free-form text is accepted
     * by Meta and then dropped outside the 24-hour window, so anything
     * business-initiated must resolve to a template here.
     *
     * @param  array<string, string>  $vars
     * @return array<string, mixed>|null
     */
    private function whatsAppMeta(string $key, ?string $fallbackName, array $vars): ?array
    {
        /** @var array{name?: string, params?: list<string>, auth?: bool}|null $map */
        $map = config('whatsapp_templates.'.$key);
        $name = $map['name'] ?? $fallbackName;

        if ($name === null) {
            return null;
        }

        $meta = ['wa_template' => $name];

        if (isset($map['params'])) {
            $meta['wa_params'] = array_values(array_map(
                static fn (string $var): string => (string) ($vars[$var] ?? ''),
                $map['params'],
            ));
        }

        if ($map['auth'] ?? false) {
            $meta['wa_auth'] = true;
        }

        return $meta;
    }

    private function guard(User $to, MessageCategory $category): ?string
    {
        if ($category === MessageCategory::Marketing && ! $this->preference($to)->marketing_opt_in) {
            return 'not_opted_in';
        }

        $q = config('messaging.quiet_hours');
        $hour = Carbon::now($q['timezone'])->hour;
        $inQuiet = $hour >= (int) $q['start'] || $hour < (int) $q['end'];
        if ($inQuiet) {
            return 'quiet_hours';
        }

        $cap = (int) config('messaging.frequency_cap', 6);
        $recent = Message::query()
            ->where('user_id', $to->id)
            ->where('direction', MessageDirection::Out->value)
            ->whereNotIn('status', [MessageStatus::Suppressed->value, MessageStatus::Blocked->value])
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($recent >= $cap) {
            return 'frequency_cap';
        }

        return null;
    }

    private function deliver(Message $message, ?User $to): void
    {
        match ($message->channel) {
            MessageChannel::WhatsApp => SendWhatsAppMessage::dispatch($message->id),
            MessageChannel::Email => SendEmailMessage::dispatch($message->id),
            MessageChannel::InApp => $this->deliverInApp($message, $to),
        };
    }

    private function deliverInApp(Message $message, ?User $to): void
    {
        if ($to !== null) {
            InAppNotification::query()->create([
                'tenant_id' => $message->tenant_id,
                'user_id' => $to->id,
                'title' => $message->subject ?? 'BrowseJobs',
                'body' => $message->body,
                'url' => $message->meta['link'] ?? null,
            ]);
        }

        $message->update(['status' => MessageStatus::Sent->value, 'sent_at' => now()]);
    }

    private function recordTimeline(Message $message, User $to, ?Lead $lead, string $body): void
    {
        $lead ??= $this->correlateLead($to);
        if ($lead === null) {
            return;
        }

        $this->timeline->handle(
            $lead,
            ContactTimelineEvent::TYPE_MESSAGE_OUT,
            $body,
            ['channel' => $message->channel->value, 'template' => $message->template_key],
        );
    }

    private function correlateLead(User $to): ?Lead
    {
        $phone = $to->phone !== null ? PhoneNormalizer::normalize($to->phone) : null;
        $hasPhone = $phone !== null && $phone !== '';
        $hasEmail = $to->email !== null && $to->email !== '';
        if (! $hasPhone && ! $hasEmail) {
            return null;
        }

        return Lead::query()
            ->where('tenant_id', $to->tenant_id)
            ->whereNull('merged_into_id')
            ->where(function ($q) use ($phone, $hasPhone, $hasEmail, $to) {
                if ($hasPhone) {
                    $q->where('phone_normalized', $phone);
                }
                if ($hasEmail) {
                    $q->orWhere('email', $to->email);
                }
            })
            ->first();
    }

    private function resolveTemplate(?int $tenantId, string $key, MessageChannel $desired): ?MessageTemplate
    {
        $templates = MessageTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->where('active', true)
            ->get();

        return $templates->firstWhere('channel', $desired) ?? $templates->first();
    }

    private function preferredChannel(User $to): MessageChannel
    {
        return $this->preference($to)->preferred_channel;
    }

    private function preference(User $to): MessagePreference
    {
        return MessagePreference::query()->firstOrNew(
            ['user_id' => $to->id],
            [
                'tenant_id' => $to->tenant_id,
                'preferred_channel' => config('messaging.default_channel', 'whatsapp'),
                'marketing_opt_in' => false,
            ],
        );
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function render(string $template, array $vars): string
    {
        $replacements = [];
        foreach ($vars as $k => $v) {
            $replacements['{{'.$k.'}}'] = $v;
        }

        return strtr($template, $replacements);
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function log(User $to, MessageTemplate $template, MessageChannel $channel, MessageCategory $category, ?string $recipient, ?string $subject, ?string $body, MessageStatus $status, ?string $reason, ?Lead $lead, ?string $link, array $vars = []): Message
    {
        return Message::query()->create([
            'tenant_id' => $to->tenant_id,
            'user_id' => $to->id,
            'lead_id' => $lead?->id,
            'direction' => MessageDirection::Out->value,
            'channel' => $channel->value,
            'template_key' => $template->key,
            'category' => $category->value,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'status' => $status->value,
            'suppressed_reason' => in_array($status, [MessageStatus::Suppressed, MessageStatus::Blocked], true) ? $reason : null,
            'meta' => array_filter(array_merge(
                ['link' => $link],
                $channel === MessageChannel::WhatsApp
                    ? ($this->whatsAppMeta($template->key, $template->name, $vars) ?? [])
                    : [],
            )) ?: null,
        ]);
    }
}

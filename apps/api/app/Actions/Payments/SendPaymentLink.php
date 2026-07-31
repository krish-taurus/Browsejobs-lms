<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\MessageChannel;
use App\Models\Instalment;
use App\Models\MessageTemplate;
use App\Support\Messaging\Messenger;
use App\Support\Razorpay\RazorpayClient;

/**
 * Creates a personalized Razorpay payment link for an instalment (PRD §6.8),
 * stores it, and **delivers it** to the student via the messaging hub (P2.4)
 * using the `payment_link` template — the "personalized payment links auto-send"
 * of PRD §5 Stage 3. Delivery no-ops gracefully if no template is configured.
 */
final readonly class SendPaymentLink
{
    public function __construct(
        private RazorpayClient $razorpay,
        private Messenger $messenger,
    ) {}

    public function handle(Instalment $instalment): Instalment
    {
        $instalment->loadMissing('feePlan.student');
        $student = $instalment->feePlan?->student;

        // Razorpay refuses a second link for the same reference_id, so a
        // resend reuses the stored link and only repeats the delivery.
        if ($instalment->razorpay_payment_link_id === null || (string) $instalment->payment_link_url === '') {
            $link = $this->razorpay->createPaymentLink(
                $instalment->amount_paise,
                "Registration fee — instalment {$instalment->seq}",
                [
                    'name' => $student?->name ?? '',
                    'email' => $student?->email ?? '',
                    'contact' => $student?->phone ?? '',
                ],
                "inst-{$instalment->id}",
            );

            $instalment->razorpay_payment_link_id = $link->id;
            $instalment->payment_link_url = $link->url;
            $instalment->save();
        }

        if ($student !== null) {
            $vars = [
                'name' => $student->name,
                'amount' => number_format($instalment->amount_paise / 100),
                'seq' => (string) $instalment->seq,
                'count' => (string) ($instalment->feePlan?->instalments()->count() ?? 1),
                'due' => $instalment->due_on?->format('d M Y') ?? 'now',
                'link' => (string) $instalment->payment_link_url,
            ];

            $this->messenger->send($student, 'payment_link', $vars);

            // Free-form WhatsApp texts are dropped outside the 24h session
            // window (until the approved template activates), so mirror the
            // link to email — a payment link must never silently vanish. The
            // template check matters: without it the messenger's fallback
            // would re-resolve the WhatsApp template and double-send there.
            $hasEmailTemplate = MessageTemplate::query()
                ->where('key', 'payment_link')
                ->where('channel', MessageChannel::Email->value)
                ->where('active', true)
                ->exists();

            if ((string) $student->email !== '' && $hasEmailTemplate) {
                $this->messenger->send($student, 'payment_link', $vars, ['channel' => MessageChannel::Email]);
            }
        }

        return $instalment;
    }
}

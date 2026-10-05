<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\MessageChannel;
use App\Models\Instalment;
use App\Models\MessageTemplate;
use App\Support\Messaging\Messenger;
use App\Support\Razorpay\RazorpayClient;
use Illuminate\Support\Str;
use Throwable;

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

        // Razorpay refuses a second link for the same reference_id, so a resend
        // reuses the stored link — but only while that link is still the right
        // one. A link goes stale when the instalment is re-priced (EMI plan
        // changed) or when the account switches Razorpay mode, and serving a
        // stale one charges the wrong amount or lands the student on a test page.
        $stale = ! $this->stillValidFor($instalment);

        if ($stale) {
            $previous = $instalment->razorpay_payment_link_id;

            $link = $this->razorpay->createPaymentLink(
                $instalment->amount_paise,
                "Registration fee — instalment {$instalment->seq}",
                [
                    'name' => $student?->name ?? '',
                    'email' => $student?->email ?? '',
                    'contact' => $student?->phone ?? '',
                ],
                // A reference id Razorpay has already seen is rejected, so a
                // replacement link carries a fresh suffix.
                $previous === null ? "inst-{$instalment->id}" : 'inst-'.$instalment->id.'-'.Str::lower(Str::random(6)),
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

    /**
     * Whether the link already stored on this instalment can still be sent:
     * Razorpay must recognise it under the CURRENT keys (a test-mode link is
     * invisible once live keys are in use), it must still be payable, and it
     * must be for the amount the instalment now asks for.
     */
    private function stillValidFor(Instalment $instalment): bool
    {
        if ($instalment->razorpay_payment_link_id === null || (string) $instalment->payment_link_url === '') {
            return false;
        }

        try {
            $link = $this->razorpay->fetchPaymentLink($instalment->razorpay_payment_link_id);
        } catch (Throwable) {
            // Unknown to Razorpay — wrong mode, or deleted. Mint a fresh one.
            return false;
        }

        if (! in_array($link['status'], ['created', 'partially_paid'], true)) {
            return false;
        }

        return $link['amount_paise'] === null || $link['amount_paise'] === $instalment->amount_paise;
    }
}

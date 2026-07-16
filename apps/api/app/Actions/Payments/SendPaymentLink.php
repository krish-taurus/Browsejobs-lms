<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Instalment;
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

        if ($student !== null) {
            $this->messenger->send($student, 'payment_link', [
                'name' => $student->name,
                'link' => $link->url,
            ]);
        }

        return $instalment;
    }
}

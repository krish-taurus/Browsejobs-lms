<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Instalment;
use App\Support\Razorpay\RazorpayClient;

/**
 * Creates a personalized Razorpay payment link for an instalment (PRD §6.8) and
 * stores it on the instalment. The actual WhatsApp/email delivery is P2.4 — this
 * stores + returns the link so the admin can send/copy it now.
 */
final readonly class SendPaymentLink
{
    public function __construct(private RazorpayClient $razorpay) {}

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

        return $instalment;
    }
}

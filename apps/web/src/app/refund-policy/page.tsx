import type { Metadata } from "next";
import { LegalPage } from "@/components/legal/LegalPage";
import { contact } from "@/content/landing";

export const metadata: Metadata = { title: "Refund Policy" };

/** The 30-day guarantee in writing (spec §3.6); flagged for legal review. */
export default function RefundPolicy() {
  return (
    <LegalPage title="Refund Policy" updated="15 July 2026">
      <h2>The 30-day money-back guarantee</h2>
      <p>
        If you ask within 30 days of paying your registration fee, we refund it
        in full — for any reason. No forms designed to make you give up, no
        partial deductions.
      </p>

      <h2>How to claim</h2>
      <p>
        Email {contact.email} or WhatsApp {contact.phone} from your registered
        contact with the subject “Refund”. We confirm receipt within 2 working
        days and process the refund to your original payment method within
        [refund processing window].
      </p>

      <h2>After 30 days</h2>
      <p>
        Registration fees are non-refundable after the 30-day window. Placement
        fees are governed by your signed enrolment agreement and arise only
        after you accept an offer.
      </p>

      <h2>Company details</h2>
      <p>
        {contact.entity} · CIN: [CIN] · GST: [GST] · {contact.address} ·{" "}
        {contact.phone}
      </p>
    </LegalPage>
  );
}

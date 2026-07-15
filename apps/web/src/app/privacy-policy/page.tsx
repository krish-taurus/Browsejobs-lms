import type { Metadata } from "next";
import { LegalPage } from "@/components/legal/LegalPage";
import { contact } from "@/content/landing";

export const metadata: Metadata = { title: "Privacy Policy" };

/**
 * DPDP Act 2023–aligned structure (spec §10). Placeholders in [brackets] are
 * for the founder to fill; the whole page is flagged for legal review before
 * launch.
 */
export default function PrivacyPolicy() {
  return (
    <LegalPage title="Privacy Policy" updated="15 July 2026">
      <p>
        This policy explains how {contact.entity} (“BrowseJobs”, “we”) collects
        and uses your personal data under India&apos;s Digital Personal Data
        Protection Act, 2023.
      </p>

      <h2>What we collect</h2>
      <ul>
        <li>Contact details you submit on our forms: name, phone, email, program of interest.</li>
        <li>Attribution data: the page and campaign (UTM) your enquiry came from.</li>
        <li>Learning data once enrolled: progress, attendance, assessment and mock-interview scores.</li>
        <li>Counselling calls — recorded and AI-monitored for quality and to keep our promises verifiable.</li>
      </ul>

      <h2>Why we collect it (purposes)</h2>
      <ul>
        <li>To contact you about the free steps you requested (counselling, masterclass, bootcamp).</li>
        <li>To run your enrolment, learning, and placement journey.</li>
        <li>To improve teaching and the syllabus engine.</li>
      </ul>

      <h2>Consent</h2>
      <p>
        Every form requires an explicit consent checkbox; we log the time and
        policy version of your consent. You may withdraw consent at any time by
        writing to {contact.email}.
      </p>

      <h2>Processors and sharing</h2>
      <ul>
        <li>Payments are processed by Razorpay; we never store your card details.</li>
        <li>Analytics: Google Analytics 4 and Meta Pixel for campaign measurement.</li>
        <li>With your consent, placement-relevant data is shared with hiring companies.</li>
      </ul>

      <h2>Retention</h2>
      <p>
        Lead data is retained for [retention window]; enrolled-student records
        for [retention window] after course completion, unless law requires
        longer.
      </p>

      <h2>Your rights</h2>
      <p>
        You may request access, correction, or erasure of your data, and may
        raise a grievance with our Grievance Officer: [Grievance Officer name],{" "}
        {contact.email}.
      </p>

      <h2>Company details</h2>
      <p>
        {contact.entity} · CIN: [CIN] · GST: [GST] · {contact.address} ·{" "}
        {contact.phone}
      </p>
    </LegalPage>
  );
}

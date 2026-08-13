import { LegalPage } from "@/components/legal/LegalPage";
import { contact, legal } from "@/content/landing";
import { pageMetadata } from "@/lib/seo";

export const metadata = pageMetadata({
  title: "Terms of Service",
  description:
    "The terms governing your use of BrowseJobs programs and platform — enrolment, fees, conduct and the commitments we put in writing.",
  path: "/terms",
});

/** Structure per spec §10; flagged for legal review before launch. */
export default function Terms() {
  return (
    <LegalPage title="Terms of Service" updated="15 July 2026">
      <p>
        These terms govern your use of browsejobs.ai and enrolment with{" "}
        {contact.entity}.
      </p>

      <h2>The free steps</h2>
      <p>
        Counselling (with a written Career Analysis Report), the live
        masterclass, and the 7-hour Python bootcamp are free and carry no
        obligation.
      </p>

      <h2>Registration and access</h2>
      <p>
        Registration is ₹30,000 (or 3 EMIs of ₹10,000), payable only after the
        free steps. Enrolment includes one year of unlimited access to your
        program from the enrolment date.
      </p>

      <h2>Placement fee</h2>
      <p>
        The placement fee equals your first 3 months&apos; CTC, due only after
        you accept an offer, paid as 6 monthly EMIs, with your ₹30,000
        registration adjusted inside it. This is governed by a signed enrolment
        agreement — not by these terms alone.
      </p>

      <h2>What we do not promise</h2>
      <p>
        Nobody can guarantee employment — the market decides. We commit, in
        writing, to the process: a syllabus rebuilt from real interviews, live
        teaching, data-scored mock interviews, and active placement support.
      </p>

      <h2>Acceptable use</h2>
      <ul>
        <li>Course content and recordings are for your personal learning only.</li>
        <li>Accounts are individual and non-transferable.</li>
      </ul>

      <h2>Company details</h2>
      <p>
        {contact.entity} · CIN: {legal.cin} · GST: {legal.gst} · {contact.address} ·{" "}
        {contact.phone}
      </p>
    </LegalPage>
  );
}

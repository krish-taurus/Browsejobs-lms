import type { Metadata } from "next";
import Link from "next/link";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { Faq } from "@/components/landing/Faq";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { Capabilities } from "@/components/employers/Capabilities";
import { EmployerHero } from "@/components/employers/EmployerHero";
import { EmployerLeadForm } from "@/components/employers/EmployerLeadForm";
import { FreeOffer } from "@/components/employers/FreeOffer";
import { Pricing } from "@/components/employers/Pricing";
import { Proctoring } from "@/components/employers/Proctoring";
import { RoleTicker } from "@/components/employers/RoleTicker";
import { SampleReport } from "@/components/employers/SampleReport";
import { TalentPool } from "@/components/employers/TalentPool";
import { TradeOffs } from "@/components/employers/TradeOffs";
import { UseCases } from "@/components/employers/UseCases";
import { Walkthrough } from "@/components/employers/Walkthrough";
import { Aurora, Section, SectionHead } from "@/components/employers/ui";
import { employerFaq, freeOffer } from "@/content/employers";

const TITLE = "For employers — free for 6 months, and every applicant is already interviewed";
const DESCRIPTION =
  "Design the interview once — skills, rubric and rounds — and every applicant arrives already assessed in a proctored session. Free for six months, then ₹200 an interview or a flat 8% of CTC on a hire — you choose.";

export const metadata: Metadata = {
  title: TITLE,
  description: DESCRIPTION,
  alternates: { canonical: "/for-employers" },
  openGraph: {
    type: "website",
    title: TITLE,
    description: DESCRIPTION,
    url: "/for-employers",
  },
  twitter: { card: "summary_large_image", title: TITLE, description: DESCRIPTION },
};

/**
 * /for-employers — the employer landing page.
 *
 * A server component: everything here is static content, and only the pieces
 * that genuinely move (the hero pipeline, the self-playing walkthrough, the
 * session monitor, the animated report, the enquiry form) are client
 * components. That keeps the page statically renderable and inside the
 * landing performance budget.
 *
 * It runs on the employer portal's own dark surfaces and blue→violet accent,
 * so the workspace an employer signs into is the page that sold it. The
 * shell's `employer` variant carries that through the nav and swaps the
 * sticky CTA, which otherwise offers a hiring manager a free masterclass.
 */
export default function ForEmployersPage() {
  return (
    <MarketingShell variant="employer">
      <EmployerHero />

      <RoleTicker />

      <FreeOffer />

      <UseCases />

      {/* Step by step ------------------------------------------------- */}
      <Section id="how-it-works" className="overflow-hidden">
        <Aurora />
        <SectionHead
          kicker="Step by step"
          title="From an empty workspace to"
          highlight="a signed offer"
          sub="Seven steps. The first four are you designing your process; after that the pipeline runs and you read what comes back. It plays itself — click any step to take over."
          tone="employer"
        />
        <Walkthrough />
      </Section>

      <Proctoring />

      <Capabilities />

      {/* Sample report ------------------------------------------------ */}
      <Section id="sample-report" className="overflow-hidden">
        <Aurora />
        <SectionHead
          kicker="The report you open"
          title="A candidate, in"
          highlight="full"
          sub="This is the screen an employer lands on after the assessment is graded — rebuilt here with a fictional candidate. Nothing has been tidied for the page: one check is still pending, one has not started, and contact details are locked until you shortlist."
        />
        <SampleReport />
      </Section>

      <TalentPool />

      <Pricing />

      <TradeOffs />

      <Faq
        items={employerFaq}
        kicker="Employer questions"
        title="Asked before every pilot"
        id="employer-faq"
        tone="dark"
      />

      {/* Enquiry ------------------------------------------------------ */}
      <section id="hire" className="relative overflow-hidden scroll-mt-24 px-5 py-16 md:py-24">
        <Aurora />
        <div className="relative mx-auto grid max-w-6xl items-start gap-10 lg:grid-cols-[1fr_0.95fr]">
          <ScrollReveal>
            <p className="kicker text-verify">
              Free for {freeOffer.months} months · ₹{freeOffer.price}
            </p>
            <h2 className="display mt-3 text-3xl text-white md:text-[2.75rem]">
              Bring us one role you are{" "}
              <span className="bg-gradient-to-r from-trust via-sky to-employer bg-clip-text text-transparent">
                struggling to fill
              </span>
            </h2>
            <p className="mt-4 max-w-lg text-[15px] leading-relaxed text-white/55">
              We will design the assessment for it with you, open a workspace, and show you the
              matched pool before you commit to anything. If the pool is thin for that role, we
              will say so on the first call rather than the fourth.
            </p>

            <ul className="mt-8 space-y-3 border-t border-white/[0.07] pt-8">
              {[
                `Six months of the full workspace at ₹${freeOffer.price}`,
                "Then ₹200 an interview, or 8% of CTC on a hire — your choice",
                "One role, designed end to end with you",
                "The matched talent pool for it, before you commit",
                "An honest read on whether this fits how you hire",
              ].map((l) => (
                <li key={l} className="flex gap-3 text-[14px] text-white/70">
                  <span aria-hidden className="text-verify">
                    ▸
                  </span>
                  {l}
                </li>
              ))}
            </ul>

            <p className="mt-8 text-[13px] leading-relaxed text-white/40">
              Nobody can guarantee a hire — the market decides that. What we put in writing is the
              process: a designed assessment, proctored sessions, consistent grading, checked
              credentials, and evidence you can audit.
            </p>

            <p className="mono mt-6 text-[11px] uppercase tracking-[0.14em] text-white/35">
              Already have a workspace?{" "}
              <Link href="/employer" className="text-trust hover:underline">
                Sign in
              </Link>
            </p>
          </ScrollReveal>

          <ScrollReveal delay={0.08}>
            <EmployerLeadForm />
          </ScrollReveal>
        </div>
      </section>
    </MarketingShell>
  );
}

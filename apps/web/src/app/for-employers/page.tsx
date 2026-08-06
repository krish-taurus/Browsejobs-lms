import type { Metadata } from "next";
import Link from "next/link";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { Faq } from "@/components/landing/Faq";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { Capabilities } from "@/components/employers/Capabilities";
import { EmployerHero } from "@/components/employers/EmployerHero";
import { EmployerLeadForm } from "@/components/employers/EmployerLeadForm";
import { SampleReport } from "@/components/employers/SampleReport";
import { TalentPool } from "@/components/employers/TalentPool";
import { TradeOffs } from "@/components/employers/TradeOffs";
import { UseCases } from "@/components/employers/UseCases";
import { Walkthrough } from "@/components/employers/Walkthrough";
import { Section, SectionHead } from "@/components/employers/ui";
import { employerFaq } from "@/content/employers";

const TITLE = "For employers — hire from candidates who have already interviewed";
const DESCRIPTION =
  "Design the interview once — skills, rubric and rounds — and every applicant arrives already assessed. Scored candidates, verified credentials from DigiLocker and EPFO, and the evidence behind every decision.";

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
 * A server component: everything here is static content, and only the three
 * genuinely interactive pieces (the walkthrough tabs, the animated report,
 * the enquiry form) are client components. That keeps the page statically
 * renderable and inside the landing performance budget.
 *
 * It is graded like the employer portal — trust blue paired with the module's
 * violet, ink panels wherever the page shows product — so the workspace an
 * employer signs into looks like the page that sold it.
 */
export default function ForEmployersPage() {
  return (
    <MarketingShell>
      <EmployerHero />

      <UseCases />

      {/* Step by step ------------------------------------------------- */}
      <Section id="how-it-works" className="bg-white">
        <SectionHead
          kicker="Step by step"
          title="From an empty workspace to"
          highlight="a signed offer"
          sub="Seven steps. The first four are you designing your process; after that the pipeline runs and you read what comes back."
          tone="employer"
        />
        <Walkthrough />
      </Section>

      <Capabilities />

      {/* Sample report ------------------------------------------------ */}
      <Section id="sample-report" className="bg-white">
        <SectionHead
          kicker="The report you open"
          title="A candidate, in"
          highlight="full"
          sub="This is the screen an employer lands on after the assessment is graded — rebuilt here with a fictional candidate. Nothing has been tidied for the page: one check is still pending, one has not started, contact details are locked, and the session panel admits what was not observed."
        />
        <SampleReport />
      </Section>

      <TalentPool />

      <TradeOffs />

      <Faq
        items={employerFaq}
        kicker="Employer questions"
        title="Asked before every pilot"
        id="employer-faq"
      />

      {/* Enquiry ------------------------------------------------------ */}
      <section id="hire" className="scroll-mt-24 bg-deck px-5 py-16 md:py-24">
        <div className="mx-auto grid max-w-6xl items-start gap-10 lg:grid-cols-[1fr_0.95fr]">
          <ScrollReveal>
            <p className="kicker text-employer">Hire from BrowseJobs</p>
            <h2 className="display mt-3 text-3xl text-white md:text-[2.75rem]">
              Bring us one role you are{" "}
              <span className="bg-gradient-to-r from-trust to-employer bg-clip-text text-transparent">
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
              process: a designed assessment, consistent grading, checked credentials, and
              evidence you can audit.
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

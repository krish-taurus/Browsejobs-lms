import Link from "next/link";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { BotGrid } from "@/components/employers/BotGrid";
import { DaysCompare } from "@/components/employers/DaysCompare";
import { HiringJourney } from "@/components/employers/HiringJourney";
import { SampleReport } from "@/components/employers/SampleReport";
import { EMPLOYER_FAQ, EMPLOYER_WAYS } from "@/content/employer-landing";
import { stagger } from "@/lib/motion";

const TRUST = [
  {
    title: "Pre-vetted, pre-interviewed",
    body: "You meet candidates who already sat the interview. Each one comes with a full performance report and their CV.",
  },
  {
    title: "Fully proctored",
    body: "Every interview is fully proctored. The report tells you what the checks saw.",
  },
  {
    title: "No surprises",
    body: "You are not opening a cold CV and hoping. The score, the notes, and the CV arrive together.",
  },
] as const;

export function EmployersPage() {
  return (
    <>
      <section id="top" className="border-b border-line bg-white">
        <div className="mx-auto grid max-w-6xl items-center gap-10 px-5 py-16 md:py-24 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
          <ScrollReveal>
            <p className="kicker text-trust">For employers</p>
            <h1 className="display mt-3 text-4xl text-ink md:text-6xl">We reverse-engineered hiring.</h1>
            <p className="mt-4 max-w-xl text-lg leading-relaxed text-ink2">
              And changed the process for the first time in 30 years. No more surprises, no more manual calls. Just a
              WhatsApp message and the bots take over.
            </p>
            <p className="mt-4 max-w-xl text-lg leading-relaxed text-ink">
              Hiring time drops from <span className="mono">90</span> days to <span className="mono">3</span> days.
            </p>
            <Disclaimer className="mt-4 max-w-xl" />
            <Link
              href="#work-with-us"
              className="mt-8 inline-flex items-center justify-center rounded-full bg-trust px-7 py-3.5 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep"
            >
              Hire with BrowseJobs
            </Link>
          </ScrollReveal>
          <ScrollReveal delay={0.07}>
            <DaysCompare />
          </ScrollReveal>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-5 py-16 md:py-24">
        <ScrollReveal>
          <p className="kicker text-trust">What you can trust</p>
          <h2 className="display mt-3 max-w-3xl text-3xl text-ink md:text-5xl">
            Pre-qualified people. A report. No surprises.
          </h2>
        </ScrollReveal>
        <ul className="mt-8 grid gap-4 md:grid-cols-3">
          {TRUST.map((item, index) => (
            <ScrollReveal key={item.title} as="li" delay={index * stagger} className="h-full">
              <article className="h-full rounded-[14px] border border-line bg-white p-5 shadow-soft">
                <h3 className="text-lg font-semibold text-ink">{item.title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-ink2">{item.body}</p>
              </article>
            </ScrollReveal>
          ))}
        </ul>
      </section>

      <section id="bots" className="scroll-mt-28 border-y border-line bg-white">
        <div className="mx-auto max-w-6xl px-5 py-16 md:py-24">
          <ScrollReveal>
            <p className="kicker text-trust">The four bots</p>
            <h2 className="display mt-3 max-w-3xl text-3xl text-ink md:text-5xl">A WhatsApp message. Then the bots.</h2>
            <p className="mt-4 max-w-2xl text-lg leading-relaxed text-ink2">
              You do not chase candidates by hand. Each bot has one job.
            </p>
          </ScrollReveal>
          <BotGrid />
        </div>
      </section>

      <section id="journey" className="scroll-mt-28">
        <div className="mx-auto max-w-6xl px-5 py-16 md:py-24">
          <ScrollReveal>
            <p className="kicker text-trust">Hiring journey</p>
            <h2 className="display mt-3 max-w-3xl text-3xl text-ink md:text-5xl">From the WhatsApp message to day 3.</h2>
            <p className="mt-4 max-w-2xl text-lg leading-relaxed text-ink2">
              The old way is 90 days, manual calls, and surprises. The BrowseJobs way is 3 days on WhatsApp bots.
            </p>
            <Disclaimer className="mt-4 max-w-2xl" />
          </ScrollReveal>
          <HiringJourney />
          <p className="mt-8 max-w-2xl text-sm leading-relaxed text-ink2">
            The hiring floor is sample data, with fictional names. We call and screen, run pre-BGV, and prepare the offer. A person always releases it.{" "}
            <Link href="/employers/mission-control-demo" className="font-semibold text-trust hover:text-deep">
              Open the demo
            </Link>
            .
          </p>
        </div>
      </section>

      <section id="work-with-us" className="scroll-mt-28 border-y border-line bg-white">
        <div className="mx-auto max-w-6xl px-5 py-16 md:py-24">
          <ScrollReveal>
            <p className="kicker text-trust">Two ways to work with us</p>
            <h2 className="display mt-3 max-w-3xl text-3xl text-ink md:text-5xl">Partner with us, or run the tool yourself.</h2>
          </ScrollReveal>
          <div className="mt-8 grid gap-4 md:grid-cols-2">
            {EMPLOYER_WAYS.map((way) => (
              <article key={way.id} className="flex flex-col rounded-[22px] border border-line bg-paper p-6 md:p-8">
                <h3 className="display text-2xl text-ink">{way.title}</h3>
                <p className="mt-3 flex-1 text-[15px] leading-relaxed text-ink2">{way.body}</p>
                <Link
                  href={way.href}
                  className={
                    way.primary
                      ? "mt-6 inline-flex items-center justify-center rounded-full bg-trust px-6 py-3.5 text-center font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep"
                      : "mt-6 inline-flex items-center justify-center rounded-full border border-line bg-white px-6 py-3.5 text-center font-semibold text-ink transition-colors hover:border-trust"
                  }
                >
                  {way.cta}
                </Link>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section id="report" className="scroll-mt-28">
        <div className="mx-auto max-w-6xl px-5 py-16 md:py-24">
          <ScrollReveal>
            <p className="kicker text-trust">Sample report: example candidate</p>
            <h2 className="display mt-3 max-w-3xl text-3xl text-ink md:text-5xl">What you receive.</h2>
            <p className="mt-4 max-w-2xl text-lg leading-relaxed text-ink2">
              A performance report and the CV, side by side. The person below is an example. The companies are examples.
              Nothing here is a real candidate.
            </p>
          </ScrollReveal>
          <SampleReport />
        </div>
      </section>

      <section id="faq" className="scroll-mt-28 border-t border-line bg-white pb-28 md:pb-24">
        <div className="mx-auto max-w-3xl px-5 py-16 md:py-24">
          <ScrollReveal>
            <p className="kicker text-trust">Questions</p>
            <h2 className="display mt-3 text-3xl text-ink md:text-5xl">Employer FAQ</h2>
          </ScrollReveal>
          <div className="mt-8 divide-y divide-line border-y border-line">
            {EMPLOYER_FAQ.map((item) => (
              <details key={item.q} className="group py-4">
                <summary className="cursor-pointer list-none text-base font-semibold text-ink marker:content-none">
                  <span className="flex items-start justify-between gap-4">
                    {item.q}
                    <span aria-hidden className="mono text-trust group-open:rotate-45">
                      +
                    </span>
                  </span>
                </summary>
                <p className="mt-3 text-sm leading-relaxed text-ink2">{item.a}</p>
              </details>
            ))}
          </div>
        </div>
      </section>
    </>
  );
}

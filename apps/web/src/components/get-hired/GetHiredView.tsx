import Link from "next/link";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { Kicker } from "@/components/brand/Kicker";
import { Wordmark } from "@/components/brand/Wordmark";
import { BookCta } from "@/components/landing/BookCta";
import { Footer } from "@/components/landing/Footer";
import { LeadModal } from "@/components/landing/LeadModal";
import { PromiseCards } from "@/components/landing/PromiseCards";
import { GET_HIRED_FAQ, WHATSAPP_SCREEN } from "@/content/get-hired";
import { courses, fees, freeLadder } from "@/content/landing";
import { HowItWorks } from "./HowItWorks";
import { ReverseHireDemo } from "./ReverseHireDemo";
import { ScreenBar } from "./ScreenBar";

export function GetHiredView() {
  const live = courses.filter((c) => c.live);
  const soon = courses.filter((c) => !c.live);

  return (
    <>
      <header className="sticky top-0 z-40 border-b border-line/80 bg-white/85 backdrop-blur-xl">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-5 py-3">
          <Link href="/" aria-label="BrowseJobs home">
            <Wordmark />
          </Link>
          <nav className="hidden items-center gap-6 md:flex">
            <a href="#how" className="text-sm font-medium text-muted hover:text-ink">
              How it works
            </a>
            <a href="#tracks" className="text-sm font-medium text-muted hover:text-ink">
              Tracks
            </a>
            <a href="#fees" className="text-sm font-medium text-muted hover:text-ink">
              Fees
            </a>
            <Link href="/employers" className="text-sm font-medium text-muted hover:text-ink">
              For employers
            </Link>
          </nav>
          <BookCta variant="counselling" ghost className="hidden px-4 py-2 text-sm md:inline-flex">
            Start free AI interview
          </BookCta>
        </div>
      </header>

      <main className="bg-white text-ink">
        <section className="mx-auto grid max-w-6xl items-center gap-10 px-5 pb-16 pt-12 md:grid-cols-[1fr_1.05fr] md:gap-14 md:px-6 md:pb-24 md:pt-20">
          <div>
            <Kicker>Free AI interview</Kicker>
            <h1 className="display mt-4 text-[2.6rem] leading-[0.98] md:text-6xl">
              Find out if you&apos;d clear the interview.
              <span className="mt-1 block text-trust">Before HR sees you.</span>
            </h1>
            <p className="mt-6 max-w-xl text-lg leading-relaxed text-muted">
              You take a free AI interview. You get a score and feedback. Clear it, and we put you in front of HR with
              your score. Miss it, and free counselling shows what&apos;s blocking you. A course comes only if you need
              it.
            </p>
            <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
              <BookCta variant="counselling" className="px-8 py-3.5">
                Start free AI interview
              </BookCta>
              <a
                href={WHATSAPP_SCREEN}
                className="rounded-full border border-line px-5 py-3 text-center text-sm font-semibold text-ink hover:border-trust"
              >
                WhatsApp us
              </a>
            </div>
            <p className="mono mt-4 text-xs text-muted">Free · You book it · No card</p>
          </div>
          <ReverseHireDemo />
        </section>

        <HowItWorks />

        <section id="tracks" className="mx-auto max-w-6xl px-6 py-20 md:py-28">
          <Kicker>If you need a course</Kicker>
          <h2 className="display mt-4 max-w-3xl text-3xl md:text-5xl">A course only if it closes the gap.</h2>
          <p className="mt-5 max-w-2xl text-lg text-muted">
            Four courses are live. Each one is built from interviews companies are actually running. Open the
            syllabus. Compare it with the jobs you want. That check is free.
          </p>
          <div className="mt-10 grid gap-4 md:grid-cols-2">
            {live.map((c) => (
              <Link
                key={c.slug}
                href={`/courses/${c.slug}`}
                className="rounded-panel border border-line bg-white p-6 transition-transform hover:-translate-y-0.5 hover:border-trust/40"
              >
                <span className="mono text-xs text-trust">{c.code}</span>
                <h3 className="display mt-3 text-2xl">{c.name}</h3>
                <p className="mt-2 text-sm text-muted">{c.tagline}</p>
                <p className="mt-4 text-sm font-semibold text-trust">View the syllabus →</p>
              </Link>
            ))}
          </div>

          <div className="mt-8 rounded-panel border border-dashed border-line bg-paper p-6 md:p-8">
            <h3 className="display text-2xl">If the gap is shaped like AI</h3>
            <p className="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
              Start with a live track that already teaches the engineering around it — Data Engineering or Python
              Backend — or talk to us. Agentic AI is not a live course page. Joining the waitlist is the honest action.
              Cyber Security and ServiceNow are waitlist too.
            </p>
            <div className="mt-5 flex flex-wrap items-center gap-3">
              <BookCta variant="waitlist" courseSlug="agentic-ai" ghost>
                Join the Agentic AI waitlist
              </BookCta>
              <span className="mono text-xs text-muted">{soon.map((c) => c.name).join(" · ")} · waitlist</span>
            </div>
          </div>
        </section>

        <section id="fees" className="border-y border-line bg-paper px-6 py-20 md:py-28">
          <div className="mx-auto max-w-6xl">
            <Kicker>Fees</Kicker>
            <h2 className="display mt-4 max-w-3xl text-3xl md:text-5xl">
              You pay registration after the free steps. The placement fee comes only after you accept an offer.
            </h2>
            <ol className="mt-10 grid gap-3 md:grid-cols-4">
              {freeLadder.map((step) => (
                <li
                  key={step.step}
                  className={`rounded-card border p-5 ${step.free ? "border-verify/30 bg-verify-bg" : "border-line bg-white"}`}
                >
                  <p className="mono text-xs text-trust">{step.step}</p>
                  <p className={`mt-2 text-sm font-semibold ${step.free ? "text-verify" : "text-ink"}`}>
                    {step.free ? "Free" : "Registration"}
                  </p>
                  <p className="mt-2 text-sm leading-relaxed text-ink">{step.title}</p>
                </li>
              ))}
            </ol>
            <div className="mt-8 grid gap-5 md:grid-cols-2">
              <div className="rounded-panel border border-line bg-white p-7">
                <p className="kicker text-muted">Registration</p>
                <p className="mono mt-3 text-4xl">₹{fees.registration.toLocaleString("en-IN")}</p>
                <p className="mt-3 text-sm leading-relaxed text-muted">
                  Payable only after the free masterclass and the free 7-hour bootcamp. Or{" "}
                  {fees.registrationEmi.months} × ₹{fees.registrationEmi.amount.toLocaleString("en-IN")}.{" "}
                  {fees.guaranteeDays}-day money-back guarantee — any reason, in writing.
                </p>
                <Link href="/masterclass" className="mt-4 inline-block text-sm font-semibold text-trust">
                  See the free masterclass →
                </Link>
              </div>
              <div className="rounded-panel bg-ink p-7 text-white">
                <p className="kicker text-sky/70">Placement fee</p>
                <p className="mt-3 text-[15px] leading-relaxed text-white/75">
                  Your first {fees.placement.monthsOfCtc} months&apos; CTC, due only after you accept an offer — paid as{" "}
                  {fees.placement.emis} monthly EMIs from the new salary. The ₹
                  {fees.placement.adjusted.toLocaleString("en-IN")} registration is adjusted inside it.
                </p>
                <p className="mono mt-4 text-[11px] text-white/40">
                  Worked example on the homepage uses a ₹12 LPA offer to show the arithmetic. It is not a salary we are
                  offering you.
                </p>
              </div>
            </div>
            <Disclaimer className="mt-6" />
          </div>
        </section>

        <section id="proof" className="mx-auto max-w-3xl px-6 py-20 md:py-28">
          <Kicker>Before recruiters</Kicker>
          <h2 className="display mt-4 text-3xl md:text-5xl">Recruiters see a score. Or they see empty.</h2>
          <div className="mt-6 space-y-4 text-[17px] leading-relaxed text-muted">
            <p>
              Before your profile goes in front of a recruiter, the interview has to be graded. Strong moments. Thin
              moments. One next step.
            </p>
            <p>
              If it has not been graded, the score stays empty. We will not write a number in to make a shortlist look
              finished, and we will not tell an employer you are ready because you paid. The bar on a role belongs to
              that employer.
            </p>
            <p>
              The hiring side of this — the interviews employers run — is a different door. If you are hiring, start at{" "}
              <Link href="/employers" className="font-semibold text-trust">
                BrowseJobs for employers
              </Link>
              .
            </p>
          </div>
          <div className="mt-8">
            <BookCta variant="counselling">Start free AI interview</BookCta>
          </div>
        </section>

        <PromiseCards />

        <section id="faq" className="mx-auto max-w-3xl px-6 py-20">
          <Kicker>Questions</Kicker>
          <h2 className="display mt-3 text-3xl md:text-5xl">Straight answers</h2>
          <div className="mt-8 divide-y divide-line border-y border-line">
            {GET_HIRED_FAQ.map((f) => (
              <details key={f.q} className="py-4">
                <summary className="cursor-pointer list-none text-[16px] font-semibold">{f.q}</summary>
                <p className="mt-3 text-[15px] leading-relaxed text-muted">{f.a}</p>
              </details>
            ))}
          </div>
        </section>
      </main>
      <Footer />
      <LeadModal />
      <ScreenBar />
    </>
  );
}

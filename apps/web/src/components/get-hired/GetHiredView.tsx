import Link from "next/link";
import { ApShell } from "@/components/ap/ApShell";
import { ApLeadButton } from "@/components/ap/pages/ApLeadButton";
import { LeadModal } from "@/components/landing/LeadModal";
import { PromiseCards } from "@/components/landing/PromiseCards";
import { GetHiredScreen } from "./GetHiredScreen";
import { GET_HIRED_FAQ, REVERSE_STEPS, WHATSAPP_SCREEN } from "@/content/get-hired";
import { courses, DISCLAIMER, fees, freeLadder } from "@/content/landing";
import "@/components/ap/pages/marketing.css";
import "@/components/ap/pages/get-hired.css";

/**
 * /get-hired — the student "interview first" page, in the Apple-direction
 * design (components/ap). The free AI interview is booked through the lead
 * modal (variant "counselling"), exactly as before.
 */
export function GetHiredView() {
  const live = courses.filter((c) => c.live);
  const soon = courses.filter((c) => !c.live);

  return (
    <ApShell current="students" cta={{ label: "Start free AI interview", href: "#top" }}>
      <section className="s-white pg-hero gh-hero" id="top">
        <div className="wrap center">
          <p className="eyebrow">Free AI interview</p>
          <h1 className="h-hero">
            <span>Find out if you&apos;d clear the interview.</span> <span className="gh-hero-sub">Before HR sees you.</span>
          </h1>
          <p className="lead">
            You take a free AI interview. You get a score and feedback. Clear it, and we put you in front of HR with your
            score. Miss it, and free counselling shows what&apos;s blocking you. A course comes only if you need it.
          </p>
          <div className="cta-row">
            <ApLeadButton variant="counselling">Start free AI interview</ApLeadButton>
            <a className="more" href={WHATSAPP_SCREEN}>
              WhatsApp us <span className="chev" aria-hidden="true">›</span>
            </a>
          </div>
          <p className="fine">Free · You book it · No card</p>
        </div>

        <div className="wrap gh-screen-wrap">
          <GetHiredScreen />
        </div>
      </section>

      <section className="s-paper chapter" id="how">
        <div className="wrap">
          <div className="pg-head">
            <p className="eyebrow">How it works</p>
            <h2 className="h-section">Three steps. Then you know.</h2>
            <p className="lead">You sit the interview. You see the score. Then HR sees you, or counselling shows the block.</p>
          </div>
          <ol className="tiles-3 gh-steps" data-reveal-kids="">
            {REVERSE_STEPS.map((step, index) => (
              <li key={step.n} className="tile tile-pad">
                <p className="eyebrow-sm">
                  {step.n} · {step.kicker}
                </p>
                <h3 className="h-card">{step.title}</h3>
                <p className="body">{step.body}</p>
                <StepDetail index={index} />
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section className="s-white chapter" id="tracks">
        <div className="wrap">
          <div className="pg-head">
            <p className="eyebrow">If you need a course</p>
            <h2 className="h-section">A course only if it closes the gap.</h2>
            <p className="lead">
              Four courses are live. Each one is built from interviews companies are actually running. Open the syllabus.
              Compare it with the jobs you want. That check is free.
            </p>
          </div>
          <ul className="pg-tiles is-2" data-reveal-kids="">
            {live.map((c) => (
              <li key={c.slug}>
                <Link href={`/courses/${c.slug}`} className="pg-tile">
                  <span className="eyebrow-sm">{c.code}</span>
                  <span className="h-card">{c.name}</span>
                  <span className="body">{c.tagline}</span>
                  <span className="more">
                    View the syllabus <span className="chev" aria-hidden="true">›</span>
                  </span>
                </Link>
              </li>
            ))}
          </ul>

          <div className="tile tile-pad gh-ai" data-reveal="">
            <h3 className="h-card">If the gap is shaped like AI</h3>
            <p className="body">
              Start with a live track that already teaches the engineering around it — Data Engineering or Python
              Backend — or talk to us. Agentic AI is not a live course page. Joining the waitlist is the honest action.
              Cyber Security and ServiceNow are waitlist too.
            </p>
            <div className="cta-row">
              <ApLeadButton variant="waitlist" courseSlug="agentic-ai" kind="secondary">
                Join the Agentic AI waitlist
              </ApLeadButton>
              <span className="fine gh-soon">{soon.map((c) => c.name).join(" · ")} · waitlist</span>
            </div>
          </div>
        </div>
      </section>

      <section className="s-paper chapter" id="fees">
        <div className="wrap">
          <div className="pg-head gh-fees-head">
            <p className="eyebrow">Fees</p>
            <h2 className="h-section">
              You pay registration after the free steps. The placement fee comes only after you accept an offer.
            </h2>
          </div>
          <div className="pg-fees">
            <ol className="pg-ladder is-4" data-reveal-kids="">
              {freeLadder.map((step) => (
                <li key={step.step} className={step.free ? "is-free" : undefined}>
                  <p className="eyebrow-sm">
                    {step.step} · {step.free ? "Free" : "Registration"}
                  </p>
                  <p className="pg-step-title">{step.title}</p>
                </li>
              ))}
            </ol>
            <div className="pg-fee-pair">
              <div className="pg-fee">
                <p className="eyebrow-sm">Registration</p>
                <p className="pg-price">₹{fees.registration.toLocaleString("en-IN")}</p>
                <p>
                  Payable only after the free masterclass and the free 7-hour bootcamp. Or {fees.registrationEmi.months} × ₹
                  {fees.registrationEmi.amount.toLocaleString("en-IN")}. {fees.guaranteeDays}-day money-back guarantee — any
                  reason, in writing.
                </p>
                <Link href="/masterclass" className="more">
                  See the free masterclass <span className="chev" aria-hidden="true">›</span>
                </Link>
              </div>
              <div className="pg-fee is-dark">
                <p className="eyebrow-sm">Placement fee</p>
                <p className="pg-fee-strong">
                  Your first {fees.placement.monthsOfCtc} months&apos; CTC, due only after you accept an offer — paid as{" "}
                  {fees.placement.emis} monthly EMIs from the new salary. The ₹
                  {fees.placement.adjusted.toLocaleString("en-IN")} registration is adjusted inside it.
                </p>
                <p className="fine">
                  Worked example on the homepage uses a ₹12 LPA offer to show the arithmetic. It is not a salary we are
                  offering you.
                </p>
              </div>
            </div>
            <p className="fine">{DISCLAIMER}</p>
          </div>
        </div>
      </section>

      <section className="s-white chapter" id="proof">
        <div className="ap-narrow">
          <div className="center">
            <p className="eyebrow">Before recruiters</p>
            <h2 className="h-section">Recruiters see a score. Or they see empty.</h2>
          </div>
          <div className="ap-prose gh-prose">
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
              <Link href="/employers" className="ap-link">
                BrowseJobs for employers
              </Link>
              .
            </p>
          </div>
          <div className="cta-row gh-center-row">
            <ApLeadButton variant="counselling">Start free AI interview</ApLeadButton>
          </div>
        </div>
      </section>

      <PromiseCards layout="chapter" />

      <section className="s-white chapter" id="faq">
        <div className="wrap">
          <div className="pg-head">
            <p className="eyebrow">Questions</p>
            <h2 className="h-section">Straight answers</h2>
          </div>
          <div className="faq">
            {GET_HIRED_FAQ.map((f) => (
              <details key={f.q}>
                <summary>
                  <h3 className="q">{f.q}</h3>
                </summary>
                <p className="answer">{f.a}</p>
              </details>
            ))}
          </div>
        </div>
      </section>
      <LeadModal />
    </ApShell>
  );
}

/** The picture under each step (formerly the dark product frame), as light detail. */
function StepDetail({ index }: { index: number }) {
  if (index === 0) {
    return (
      <div className="gh-detail">
        <p className="gh-quote">“Tell me about a pipeline you owned. What broke, and what did you change?”</p>
        <p className="fine">You book it. Not a self-serve button.</p>
      </div>
    );
  }
  if (index === 1) {
    return (
      <div className="gh-detail">
        <ul className="gh-bars">
          {[
            ["Technical depth", 0.82],
            ["Communication", 0.7],
            ["Evidence of experience", 0.88],
          ].map(([label, amount]) => (
            <li key={String(label)}>
              <div className="gh-bar-label">
                <span>{label}</span>
                <span>Sample</span>
              </div>
              <div className="gh-bar" aria-hidden="true">
                <i style={{ transform: `scaleX(${amount})` }} />
              </div>
            </li>
          ))}
        </ul>
        <p className="fine">Empty until graded. This fill is a picture of the screen.</p>
      </div>
    );
  }
  return (
    <div className="gh-detail gh-ways">
      <p>
        <b>Clear</b>
        We put you in front of HR with your score. Not a job offer.
      </p>
      <p>
        <b>Not clear</b>
        Free counselling shows what&apos;s blocking you. A course only if you need it.
      </p>
    </div>
  );
}

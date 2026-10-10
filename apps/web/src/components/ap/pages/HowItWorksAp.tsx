import Link from "next/link";
import { InterviewStartForm } from "@/components/auth/InterviewStartForm";
import { ApShell } from "@/components/ap/ApShell";
import { ApJourneyFilm } from "@/components/ap/ApFilm";
import { ApCourseTiles } from "@/components/ap/ApCourseTiles";
import { HowItWorksLeadButton } from "@/components/ap/pages/HowItWorksLeadButton";
import { LeadModal } from "@/components/landing/LeadModal";
import { CLAIM_INTERVIEW_CALL, CLAIM_RECRUITERS, recommendedCourses, recruiterFaqs, screenSteps } from "@/content/home";
import { DISCLAIMER, faqs, fees, freeLadder, promisesKept, promisesNever } from "@/content/landing";
import "./how-it-works.css";

const rupee = (n: number) => "₹" + n.toLocaleString("en-IN");

function Fine({ className = "fine" }: { className?: string }) {
  return <p className={className}>{DISCLAIMER}</p>;
}

/** /how-it-works in the Apple-direction design: interview, score, then what happens either way. */
export function HowItWorksAp() {
  const tracks = recommendedCourses();

  return (
    <ApShell>
      {/* Hero */}
      <section className="s-white hiw-hero" id="hiw-top">
        <div className="wrap center">
          <p className="eyebrow">How it works</p>
          <h1 className="h-hero hiw-title">
            <span>Interview.</span> <span>Score.</span> <span>Get seen.</span>
          </h1>
          <p className="lead">The free AI interview is the front door. Everything else on this page is what happens after the score.</p>
          <ul className="hiw-facts" aria-label="The interview at a glance">
            <li>
              <b>About 15 questions</b>From your CV
            </li>
            <li>
              <b>A score out of 100</b>With notes on weak answers
            </li>
            <li>
              <b>75% or more</b>HR recruiters see your CV
            </li>
          </ul>
          <InterviewStartForm tone="ap" id="interview-start" />
          <div className="cta-row hiw-hero-links">
            <a className="more" href="#how">
              See the three steps <span className="chev" aria-hidden="true">›</span>
            </a>
            <a className="more" href="#gaps">
              If you don&apos;t clear <span className="chev" aria-hidden="true">›</span>
            </a>
          </div>
        </div>
      </section>

      {/* The 41-second candidate film */}
      <ApJourneyFilm />

      {/* For candidates: the claims and the three steps */}
      <section className="s-white chapter" id="how">
        <div className="wrap center">
          <p className="eyebrow">For candidates</p>
          <h2 className="h-section">Clear it. Then HR sees you.</h2>
          <p className="lead">You sit the interview first. You get a score. A course comes later, and only if you need it.</p>
        </div>

        <div className="wrap">
          <div className="tiles-2 hiw-claims" data-reveal-kids="">
            <article className="tile tile-pad hiw-claim">
              <p className="eyebrow-sm">Score 75% or more</p>
              <p className="hiw-big num">3,000</p>
              <p className="hiw-big-unit">HR recruiters</p>
              <p className="body">{CLAIM_RECRUITERS}</p>
            </article>
            <article className="tile tile-pad hiw-claim">
              <p className="eyebrow-sm">Clear the interview</p>
              <p className="hiw-big num">
                <small>almost</small> 60%
              </p>
              <p className="hiw-big-unit">better chance of a call</p>
              <p className="body">{CLAIM_INTERVIEW_CALL}</p>
            </article>
          </div>
          <div className="center">
            <Fine />
          </div>
        </div>

        <div className="wrap">
          <ol className="tiles-3 hiw-steps" data-reveal-kids="">
            {screenSteps.map((step) => (
              <li key={step.id} className="tile tile-pad hiw-step" id={step.id}>
                <p className="eyebrow-sm num">Step {step.n}</p>
                <h3 className="h-card">{step.title}</h3>
                <p className="body">{step.body}</p>
                <div className="hiw-ui" aria-hidden="true">
                  {step.mode === "interview" && <InterviewSample />}
                  {step.mode === "score" && <ScoreSample />}
                  {step.mode === "outcome" && <OutcomeSample />}
                </div>
              </li>
            ))}
          </ol>
          <p className="fine center hiw-sample-note">The screens above are samples. They are not your result.</p>
        </div>
      </section>

      {/* If you don't clear */}
      <section className="s-paper chapter" id="gaps">
        <div className="wrap hiw-split">
          <div className="hiw-split-text">
            <p className="eyebrow">If you don&apos;t clear</p>
            <h2 className="h-section hiw-h2">Didn&apos;t clear? Here&apos;s what&apos;s blocking interviews.</h2>
            <p className="lead">
              A miss still helps. You see the questions that slipped, and the skills that role is hiring for. Free counselling walks you through what&apos;s
              blocking you. A course is only there if you need it to fix that.
            </p>
            <div className="cta-row">
              <HowItWorksLeadButton variant="counselling">Book free counselling</HowItWorksLeadButton>
              <a className="more" href="#courses">
                See courses that fix the gap <span className="chev" aria-hidden="true">›</span>
              </a>
            </div>
            <p className="fine">Counselling is free. You leave with a written Career Analysis Report whether you join or not.</p>
          </div>

          <div className="tile tile-pad hiw-fix" data-reveal="">
            <div className="hiw-fix-head">
              <p className="eyebrow-sm">What you might need to fix</p>
              <span className="hiw-tag">Sample</span>
            </div>
            <p className="body">Not your result. This is how a miss is written, and which live course we point you to.</p>
            <ul className="hiw-fix-list">
              {tracks.map((track) => (
                <li key={track.slug}>
                  <p>{track.when}</p>
                  <Link className="more" href={track.href}>
                    {track.name} <span className="chev" aria-hidden="true">›</span>
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </section>

      {/* Courses */}
      <section className="s-white chapter" id="courses">
        <div className="wrap center">
          <p className="eyebrow">Only if the interview says you need one</p>
          <h2 className="h-section">Courses that fix a specific gap.</h2>
          <p className="lead">
            These are not the front door. We point you here only when the interview shows that skill is where you&apos;re stuck. Every link is a live course
            page.
          </p>
        </div>
        <div className="wrap hiw-courses">
          <ApCourseTiles />
        </div>
        <div className="wrap center">
          <div className="cta-row">
            <Link className="btn btn-primary" href="/courses">
              Explore courses
            </Link>
            <a className="more" href="#hiw-top">
              Take the free AI interview first <span className="chev" aria-hidden="true">›</span>
            </a>
          </div>
        </div>
      </section>

      {/* Proof before HR */}
      <section className="s-black chapter" id="proof">
        <div className="wrap hiw-split">
          <div className="hiw-split-text">
            <p className="eyebrow">Proof before HR</p>
            <h2 className="h-section hiw-h2">HR sees your scored interview. Not a CV on its own.</h2>
            <p className="lead">
              We put you in front of HR only after you clear. If you don&apos;t, you stay with what you need to fix. Nobody can guarantee employment. The
              market decides. What we put in writing is the process.
            </p>
            <ol className="hiw-points">
              <li>
                <span className="num">01</span>You clear, and we put you in front of HR with your score.
              </li>
              <li>
                <span className="num">02</span>They see the scored interview, not a cold CV.
              </li>
              <li>
                <span className="num">03</span>You don&apos;t clear, and we don&apos;t send your profile and hope.
              </li>
            </ol>
          </div>

          <div className="tile tile-pad hiw-hr" data-reveal="">
            <div className="hiw-fix-head">
              <p className="eyebrow-sm">What HR receives</p>
              <span className="hiw-tag">Sample</span>
            </div>
            <dl className="hiw-hr-rows">
              <div>
                <dt>Role</dt>
                <dd>Data Engineer</dd>
              </div>
              <div>
                <dt>Screen</dt>
                <dd className="is-clear">Clear</dd>
              </div>
              <div>
                <dt>Queue</dt>
                <dd>Priority</dd>
              </div>
            </dl>
            <div className="hiw-hr-attached">
              <p className="eyebrow-sm">Attached</p>
              <p>Scored interview, not a CV on its own</p>
            </div>
            <p className="fine">If you don&apos;t clear, this card is not sent. You keep the note on what to fix.</p>
          </div>
        </div>
      </section>

      {/* Free steps and fees */}
      <section className="s-paper chapter" id="free-steps">
        <div className="wrap center">
          <p className="eyebrow">Three free steps first</p>
          <h2 className="h-section">You pay nothing until you have seen the work.</h2>
        </div>
        <div className="wrap-wide">
          <ol className="hiw-ladder" data-reveal-kids="">
            {freeLadder.map((rung) => (
              <li key={rung.step} className={`tile tile-pad hiw-rung${rung.free ? "" : " is-paid"}`}>
                <p className="eyebrow-sm num">{rung.free ? `Free · Step ${rung.step}` : `Step ${rung.step}`}</p>
                <h3 className="h-card">{rung.title}</h3>
                <p className="body">{rung.body}</p>
              </li>
            ))}
          </ol>
        </div>

        <div className="wrap-wide" id="fees">
          <div className="tiles-2 hiw-fees" data-reveal-kids="">
            <article className="tile tile-pad">
              <p className="eyebrow-sm">Registration</p>
              <p className="hiw-price num">{rupee(fees.registration)}</p>
              <p className="hiw-price-sub num">
                or {fees.registrationEmi.months} × {rupee(fees.registrationEmi.amount)}
              </p>
              <p className="body">
                You pay this only after the free masterclass and the free 7-hour bootcamp. Or split it: {fees.registrationEmi.months} ×{" "}
                {rupee(fees.registrationEmi.amount)}.
              </p>
            </article>
            <article className="tile tile-pad">
              <p className="eyebrow-sm">Placement fee</p>
              <p className="hiw-price hiw-price-words">After you accept an offer.</p>
              <p className="body">
                This is your first {fees.placement.monthsOfCtc} months&apos; salary, and you pay it only after you take the offer. Split across{" "}
                {fees.placement.emis} months. The {rupee(fees.placement.adjusted)} you already paid comes off that bill. {fees.guaranteeDays}-day money-back,
                for any reason, in writing.
              </p>
            </article>
          </div>
        </div>
      </section>

      {/* In writing */}
      <section className="s-white chapter" id="verify">
        <div className="wrap center">
          <p className="eyebrow">In writing</p>
          <h2 className="h-section">What we promise, and what we never will.</h2>
        </div>
        <div className="wrap">
          <div className="tiles-2 hiw-promises" data-reveal-kids="">
            <article className="tile tile-pad">
              <h3 className="h-card">What we promise in writing</h3>
              <ul className="ticks">
                {promisesKept.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            </article>
            <article className="tile tile-pad">
              <h3 className="h-card">What we will never tell you</h3>
              <ul className="ticks hiw-never">
                {promisesNever.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            </article>
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section className="s-paper chapter" id="faq">
        <div className="wrap center">
          <p className="eyebrow">Questions</p>
          <h2 className="h-section">Straight answers.</h2>
          <div className="faq hiw-faq">
            {recruiterFaqs.map((item) => (
              <details key={item.q}>
                <summary>
                  <h3 className="q">{item.q}</h3>
                </summary>
                <p className="answer">{item.a}</p>
              </details>
            ))}
          </div>
          <Fine />
          <h3 className="h-tile hiw-faq-sub">Fees, classes and refunds.</h3>
          <div className="faq hiw-faq">
            {faqs.map((item) => (
              <details key={item.q}>
                <summary>
                  <h3 className="q">{item.q}</h3>
                </summary>
                <p className="answer">{item.a}</p>
              </details>
            ))}
          </div>
          <Fine />
        </div>
      </section>

      {/* Close */}
      <section className="s-white chapter closing" id="close">
        <div className="wrap center">
          <h2 className="h-hero">
            <span>Start with</span> <span>the interview.</span>
          </h2>
          <p className="lead">The course can wait until you know what to fix.</p>
          <div className="cta-row">
            <a className="btn btn-primary" href="#hiw-top">
              Take your free AI interview
            </a>
            <HowItWorksLeadButton variant="masterclass" className="btn btn-secondary">
              Book the free masterclass
            </HowItWorksLeadButton>
          </div>
        </div>
      </section>

      <LeadModal />
    </ApShell>
  );
}

/* ---------- light sample screens (static, labelled as samples) ---------- */

function InterviewSample() {
  return (
    <>
      <div className="hiw-ui-bar">
        <span>AI interview</span>
        <span className="hiw-live">Live round</span>
      </div>
      <p className="hiw-bubble">
        <small>Interviewer</small>Walk me through a pipeline you would ship this month.
      </p>
      <p className="hiw-bubble is-you">
        <small>You</small>Land the raw files, transform them, and load a warehouse the analyst can trust.
      </p>
      <div className="hiw-wave">
        {Array.from({ length: 14 }, (_, i) => (
          <i key={i} />
        ))}
      </div>
    </>
  );
}

function ScoreSample() {
  const rows = [
    { label: "Depth", value: "Holds", good: true },
    { label: "Communication", value: "Mixed", good: false },
    { label: "Role skills", value: "Gap", good: false },
  ];
  return (
    <>
      <div className="hiw-ui-bar">
        <span>Screen score</span>
        <span>Sample</span>
      </div>
      <p className="hiw-ui-fine">Not a placement rate</p>
      <ul className="hiw-rows">
        {rows.map((row) => (
          <li key={row.label}>
            <span>{row.label}</span>
            <b className={row.good ? "is-good" : "is-gap"}>{row.value}</b>
          </li>
        ))}
      </ul>
      <p className="hiw-note">Coach note: what to fix, not a rejection. Window functions and pipeline design need another pass.</p>
    </>
  );
}

function OutcomeSample() {
  return (
    <>
      <div className="hiw-ui-bar">
        <span>Two outcomes</span>
      </div>
      <div className="hiw-outcome is-clear">
        <small>75% or more · Clear</small>
        <b>We put you in front of HR</b>
        <span>Your score goes with you. Hiring teams see the interview, not a cold CV.</span>
      </div>
      <div className="hiw-outcome">
        <small>Below 75% · Not clear</small>
        <b>What&apos;s blocking you</b>
        <span>We name what got in the way. We don&apos;t pretend you cleared.</span>
      </div>
    </>
  );
}

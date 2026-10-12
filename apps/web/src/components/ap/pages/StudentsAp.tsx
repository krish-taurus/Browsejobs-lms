import Link from "next/link";
import { ApShell } from "@/components/ap/ApShell";
import { ApJourneyFilm } from "@/components/ap/ApFilm";
import { ApCounselForm } from "@/components/ap/ApCounselForm";
import { ApCourseTiles } from "@/components/ap/ApCourseTiles";
import { InterviewStartForm } from "@/components/auth/InterviewStartForm";
import { careerCourseCards } from "@/content/courses";
import {
  BELOW_SEVENTY_FIVE,
  CLAIM_INTERVIEW_CALL,
  CLAIM_RECRUITERS,
  COUNSELLING_COPY,
  SEVENTY_FIVE,
  recruiterFaqs,
} from "@/content/home";
import { SITE_ORIGIN } from "@/lib/seo";
import { StudentsProofAp } from "./StudentsProofAp";
import "./students.css";

const DISCLAIMER =
  "Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.";

function Fine() {
  return <p className="fine">{DISCLAIMER}</p>;
}

const HOW_STEPS = [
  { kicker: "Interview", title: "Interview.", body: "About 15 questions from your CV. You answer them. It is free." },
  { kicker: "Score", title: "Score.", body: "The AI scores the round out of 100. 75% or more is a clear." },
  {
    kicker: "Get seen",
    title: "Get seen.",
    body: "A clear sends your CV to 3,000 HR recruiters. Under 75%, the path is counselling, a plan, then a retake.",
  },
] as const;

const AFTER_STEPS = [
  { kicker: "Pre-qualified", title: "You are pre-qualified.", body: "75% or more is the clear mark. Employers see people who already cleared." },
  { kicker: "CV sent", title: "Your CV is sent.", body: CLAIM_RECRUITERS },
  { kicker: "Interview call", title: "The call is more likely.", body: `${CLAIM_INTERVIEW_CALL} The market still decides.` },
] as const;

/** Where each "What 75% means" row lights up as the score scene scrolls. */
const SCORE_AT = ["0", "0.22", "0.6", "0.74", "0.88"] as const;

function Hero() {
  return (
    <section className="s-white hero" id="top">
      <div className="wrap center">
        <p className="eyebrow">For students and job seekers</p>
        <h1 className="h-hero">
          <span>Take a free</span> <span>AI interview.</span>
        </h1>
        <p className="lead">It&apos;s free. About 15 questions from your CV. Score 75% and your CV is sent to 3,000 HR recruiters.</p>
        <Fine />
        <InterviewStartForm tone="ap" id="interview-start" />
        <div className="cta-row st-hero-links">
          <a className="more" href="#how">
            See how it works <span className="chev" aria-hidden="true">›</span>
          </a>
          <a className="more" href="#counselling">
            Talk to a counsellor <span className="chev" aria-hidden="true">›</span>
          </a>
        </div>
      </div>
      <div className="hero-stage" data-scene="hero">
        <div className="hero-chip hero-chip--left" aria-hidden="true">
          <b>Free</b>About 15 questions from your CV
        </div>
        <div className="hero-chip hero-chip--right" aria-hidden="true">
          <b>75% is a clear</b>HR sees your CV
        </div>
        <div className="phone">
          <div className="phone-screen">
            <p className="eyebrow-sm">Sample result</p>
            <p className="ui-q">Your AI interview score.</p>
            <div className="st-dial" aria-hidden="true">
              <svg className="ring" viewBox="0 0 120 120">
                <circle className="ring-track" cx="60" cy="60" r="54" pathLength="100" />
                <circle className="ring-fill" cx="60" cy="60" r="54" pathLength="100" />
              </svg>
              <div className="st-dial-read">
                <span className="st-dial-num">75%</span>
                <span className="st-dial-label">Clear</span>
              </div>
            </div>
            <ul className="st-ui-list">
              <li>About 15 questions answered</li>
              <li>Notes on weak answers</li>
              <li>Ready for HR</li>
            </ul>
            <p className="ui-fine">A sample screen. Not a promise of a job.</p>
          </div>
        </div>
      </div>
    </section>
  );
}

function How() {
  return (
    <section className="s-white chapter" id="how">
      <div className="wrap">
        <div className="center">
          <p className="eyebrow">Three steps</p>
          <h2 className="h-section">How it works.</h2>
        </div>
        <ol className="tiles-3 st-tiles" data-reveal-kids="">
          {HOW_STEPS.map((step, index) => (
            <li key={step.kicker} className="tile tile-pad">
              <p className="eyebrow-sm num">
                {String(index + 1).padStart(2, "0")} · {step.kicker}
              </p>
              <h3 className="h-tile">{step.title}</h3>
              <p className="body">{step.body}</p>
            </li>
          ))}
        </ol>
        <div className="center">
          <Fine />
        </div>
      </div>
    </section>
  );
}

function What75() {
  return (
    <section className="s-paper scene st-score" data-scene="score" data-len="300vh" id="what-75">
      <div className="scene-pin chapter">
        <div className="wrap">
          <div className="center">
            <h2 className="h-section">What 75% means.</h2>
            <p className="lead">Plain version. The score is a read of this interview. It is not a job offer.</p>
          </div>
          <div className="score-grid">
            <div className="score-dial">
              <svg className="ring" viewBox="0 0 120 120" aria-hidden="true">
                <circle className="ring-track" cx="60" cy="60" r="54" pathLength="100" />
                <circle className="ring-fill" cx="60" cy="60" r="54" pathLength="100" />
              </svg>
              <div className="score-read">
                <span className="big">75%</span>
                <span className="label">Clear</span>
              </div>
            </div>
            <ol className="steps">
              {SEVENTY_FIVE.map((item, index) => (
                <li key={item.title} data-at={SCORE_AT[index] ?? "0"}>
                  <span className="n">{String(index + 1).padStart(2, "0")}</span>
                  <h3>{item.title}</h3>
                  <p>{item.body}</p>
                </li>
              ))}
            </ol>
          </div>
          <div className="center">
            <Fine />
          </div>
        </div>
      </div>
    </section>
  );
}

function AfterClear() {
  return (
    <section className="s-black chapter" id="after-clear">
      <div className="wrap">
        <div className="center">
          <p className="eyebrow">Score 75% or more</p>
          <h2 className="h-section">After you clear.</h2>
        </div>
        <ol className="tiles-3 st-tiles" data-reveal-kids="">
          {AFTER_STEPS.map((step, index) => (
            <li key={step.kicker} className="tile tile-pad">
              <p className="eyebrow-sm num">
                {String(index + 1).padStart(2, "0")} · {step.kicker}
              </p>
              <h3 className="h-tile">{step.title}</h3>
              <p className="body">{step.body}</p>
            </li>
          ))}
        </ol>
        <div className="center">
          <Fine />
        </div>
      </div>
    </section>
  );
}

function Below75() {
  const last = BELOW_SEVENTY_FIVE.length - 1;
  return (
    <section className="s-white chapter" id="below-75">
      <div className="wrap path-grid">
        <div className="path-head">
          <h2 className="h-section">Below 75%? Here&apos;s your path.</h2>
          <p className="lead">A score under 75% is a starting point. This is the path back to a clear, and the retake is free.</p>
          <Fine />
        </div>
        <ol className="path" data-scene="path">
          <span className="path-rail" aria-hidden="true"></span>
          <span className="path-fill" aria-hidden="true"></span>
          <span className="path-light" aria-hidden="true"></span>
          {BELOW_SEVENTY_FIVE.map((step, index) => (
            <li key={step.title} className={index === last ? "is-final" : undefined}>
              <span className="n">{String(index + 1).padStart(2, "0")}</span>
              <h3>{step.title}</h3>
              <p>{step.body}</p>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}

function Courses() {
  const cards = careerCourseCards();
  const itemList = {
    "@context": "https://schema.org",
    "@type": "ItemList",
    name: "Career-driven courses",
    itemListElement: cards.map((course, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: course.name,
      url: `${SITE_ORIGIN}${course.href}`,
    })),
  };
  return (
    <section className="s-paper chapter" id="career-courses">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(itemList) }} />
      <div className="wrap center">
        <p className="eyebrow">Development plan</p>
        <h2 className="h-section">Career-driven courses.</h2>
        <p className="lead">
          Courses focused on getting you hired. Real-world scenarios: projects, mock interviews, and the skills recruiters test for.
        </p>
      </div>
      <div className="wrap st-courses">
        <ApCourseTiles />
      </div>
      <div className="wrap center">
        <div className="cta-row">
          <Link className="btn btn-primary" href="/courses">
            Explore courses
          </Link>
          <a className="more" href="#counselling">
            Book free counselling <span className="chev" aria-hidden="true">›</span>
          </a>
          <a className="more" href="#interview-start">
            Take the free AI interview <span className="chev" aria-hidden="true">›</span>
          </a>
        </div>
      </div>
    </section>
  );
}

function Counselling() {
  return (
    <section className="s-white chapter" id="counselling">
      <div className="wrap">
        <div className="tile form-tile">
          <div>
            <p className="eyebrow">{COUNSELLING_COPY.kicker}</p>
            <h2 className="h-section st-form-title">{COUNSELLING_COPY.title}</h2>
            <p className="lead">{COUNSELLING_COPY.body}</p>
            <ul className="ticks">
              <li>Book a free counselling session.</li>
              <li>A BrowseJobs counsellor calls you back.</li>
              <li>Tell us when to phone.</li>
            </ul>
          </div>
          <div>
            <ApCounselForm />
          </div>
        </div>
      </div>
    </section>
  );
}

function Faq() {
  return (
    <section className="s-paper chapter" id="faq">
      <div className="wrap center">
        <h2 className="h-section">Questions.</h2>
        <div className="faq">
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
      </div>
    </section>
  );
}

function Closing() {
  return (
    <section className="s-white chapter closing">
      <div className="wrap center">
        <h2 className="h-hero">
          <span>Start with the</span> <span>interview.</span>
        </h2>
        <p className="lead">It&apos;s free. All it takes is your CV and about 15 questions.</p>
        <div className="cta-row">
          <a className="btn btn-primary" href="#interview-start">
            Take your free AI interview
          </a>
          <a className="more" href="#counselling">
            Talk to a counsellor <span className="chev" aria-hidden="true">›</span>
          </a>
        </div>
        <Fine />
      </div>
    </section>
  );
}

/** /students in the approved Apple-direction design (Oct 2026). */
export function StudentsAp() {
  return (
    <ApShell current="students" cta={{ label: "Take the free AI interview", href: "#top" }}>
      <Hero />
      <ApJourneyFilm />
      <How />
      <What75 />
      <AfterClear />
      <Below75 />
      <Courses />
      <Counselling />
      <StudentsProofAp />
      <Faq />
      <Closing />
    </ApShell>
  );
}

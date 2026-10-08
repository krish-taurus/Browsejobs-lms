"use client";

import { useEffect, useRef } from "react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { InterviewStartForm } from "@/components/auth/InterviewStartForm";
import { MacFloor } from "@/components/apple/MacFloor";
import { ArgusButton, ArgusFaq } from "@/components/argus/ui";
import { ScenePin, useScrub } from "@/components/argus/ScenePin";
import { SplitHeading } from "@/components/argus/SplitHeading";
import { CountUp } from "@/components/argus/CountUp";
import { BELOW_SEVENTY_FIVE, CLAIM_INTERVIEW_CALL, CLAIM_RECRUITERS, SEVENTY_FIVE } from "@/content/home";
import { prefersReducedMotion } from "@/lib/motion";

if (typeof window !== "undefined") gsap.registerPlugin(ScrollTrigger);

const USUALLY = [
  "Sourcing",
  "Screening calls",
  "Scheduling rounds",
  "Interviews",
  "Background checks",
  "The offer",
  "Dropouts",
] as const;

const WITH_US = [
  "Pre-qualified candidates who already cleared the AI interview",
  "We call and screen shortlisted candidates",
  "L1 and L2 rounds run for you",
  "Pre-BGV",
  "An offer ready for your approval",
] as const;

const GETS = [
  {
    title: "Who does what.",
    body: "BrowseJobs, the team and the tools, runs sourcing through pre-BGV. You meet people who already cleared. A person always releases the offer.",
  },
  {
    title: "What you see.",
    body: "The hiring floor shows the role, the shortlist, calls, rounds, pre-BGV, and the offer waiting for you. The floor on this site is sample data.",
  },
  {
    title: "The outcome.",
    body: "Time saved. Fewer interviews for your team. Only pre-qualified candidates. You still decide the offer.",
  },
] as const;

export function HomeHero() {
  const root = useRef<HTMLElement>(null);
  const word = useRef<HTMLParagraphElement>(null);

  useEffect(() => {
    const letters = word.current?.querySelectorAll("[data-letter]");
    const section = root.current;
    if (!letters?.length || !section) return;
    if (prefersReducedMotion()) {
      gsap.set(letters, { opacity: 1, y: 0 });
      return;
    }
    const intro = gsap.from(letters, {
      y: 14,
      opacity: 0,
      duration: 0.8,
      ease: "power3.out",
      stagger: 0.06,
      delay: 0.15,
    });
    const leave = gsap.to(word.current, {
      letterSpacing: "0.42em",
      opacity: 0,
      ease: "none",
      scrollTrigger: { trigger: section, start: "top top", end: "bottom top", scrub: 1 },
    });
    return () => {
      intro.kill();
      leave.scrollTrigger?.kill();
      leave.kill();
    };
  }, []);

  return (
    <section id="top" ref={root} className="argus-section argus-hero" data-scene="ring" data-anchor="top" data-progress="leave">
      <div className="argus-hero-grid">
        <div>
          <p ref={word} className="argus-hero-word" aria-label="BrowseJobs">
            <span aria-hidden="true">
              {"BROWSEJOBS".split("").map((letter, index) => (
                <span key={`${letter}-${index}`} data-letter>
                  {letter}
                </span>
              ))}
            </span>
          </p>
          <div className="argus-scrim">
            <SplitHeading as="h1" className="argus-h1" text="Take a free AI interview." breakAfter={2} />
            <p className="argus-body argus-hero-sub">
              It&apos;s free. About 15 questions from your CV. A score of 75% or more puts you in front of HR.
            </p>
            <Disclaimer tone="argus" />
            <InterviewStartForm id="interview-start" tone="argus" />
          </div>
        </div>
        <figure className="argus-sample">
          <div className="argus-sample-card">
            <p className="argus-kicker">Sample question</p>
            <p className="argus-sample-q">Walk me through a pipeline you would ship this month.</p>
            <div className="argus-mini-ring" aria-hidden>
              <span>75%</span>
            </div>
            <p className="argus-kicker">Clear mark</p>
          </div>
          <figcaption className="argus-disclaimer">A sample round. Not a promise of a job.</figcaption>
        </figure>
      </div>
    </section>
  );
}

function ScoreBoard() {
  const num = useRef<HTMLSpanElement>(null);
  const clear = useRef<HTMLParagraphElement>(null);
  const list = useRef<HTMLOListElement>(null);

  useScrub((timeline) => {
    timeline.eventCallback("onUpdate", () => {
      const p = timeline.progress();
      if (num.current) num.current.textContent = `${Math.round(Math.min(1, p / 0.4) * 75)}%`;
      if (clear.current) clear.current.style.opacity = p >= 0.4 ? "1" : "0";
      list.current?.querySelectorAll<HTMLLIElement>("li").forEach((item, index) => {
        const on = p >= index / 5;
        item.style.opacity = on ? "1" : "0.38";
        item.classList.toggle("is-hot", index === 2 && p >= 0.4);
      });
    });
  });

  return (
    <div className="argus-score-layout">
      <div className="argus-scrim argus-score-copy">
        <h2 className="argus-h2">What 75% means.</h2>
        <p className="argus-body">Plain version. The score is a read of this interview. It is not a job offer.</p>
        <p className="argus-score-num">
          <span ref={num} className="argus-count argus-score-scrub">
            0%
          </span>
          <span className="argus-score-count">
            <CountUp to={75} suffix="%" />
          </span>
        </p>
        <p ref={clear} className="argus-clear argus-score-clear">
          Clear
        </p>
      </div>
      <ol ref={list} className="argus-score-cards">
        {SEVENTY_FIVE.map((item, index) => (
          <li key={item.title} className={index === 2 ? "is-mark" : undefined}>
            <p className="argus-kicker">{String(index + 1).padStart(2, "0")}</p>
            <h3>{item.title}</h3>
            <p>{item.body}</p>
          </li>
        ))}
      </ol>
      <Disclaimer tone="argus" />
    </div>
  );
}

export function HomeScore() {
  return (
    <ScenePin id="what-75" scene="score" anchor="halo" className="argus-section argus-score">
      <ScoreBoard />
    </ScenePin>
  );
}

export function HomePath() {
  const root = useRef<HTMLElement>(null);
  const beam = useRef<SVGPathElement>(null);
  const light = useRef<HTMLSpanElement>(null);

  useEffect(() => {
    const section = root.current;
    const path = beam.current;
    if (!section || !path) return;
    const length = path.getTotalLength();
    path.style.strokeDasharray = `${length}`;
    if (prefersReducedMotion()) {
      path.style.strokeDashoffset = "0";
      return;
    }
    path.style.strokeDashoffset = `${length}`;
    const tween = gsap.to(path, {
      strokeDashoffset: 0,
      ease: "none",
      scrollTrigger: {
        trigger: section,
        start: "top 70%",
        end: "bottom 60%",
        scrub: 1,
        onUpdate: (self) => {
          if (light.current) light.current.style.top = `${self.progress * 100}%`;
          section.querySelectorAll<HTMLLIElement>("li").forEach((item, index) => {
            item.classList.toggle("is-on", self.progress >= index / 7);
          });
        },
      },
    });
    return () => {
      tween.scrollTrigger?.kill();
      tween.kill();
    };
  }, []);

  return (
    <section id="below-75" ref={root} className="argus-section argus-path" data-scene="sphere" data-anchor="right">
      <div className="argus-scrim">
        <h2 className="argus-h2">Below 75%? Here&apos;s your path.</h2>
        <p className="argus-body">A score under 75% is a starting point. This is the path back to a clear.</p>
      </div>
      <div className="argus-path-row">
        <svg className="argus-path-line" viewBox="0 0 40 700" preserveAspectRatio="none" aria-hidden>
          <path ref={beam} d="M20 8 V692" />
        </svg>
        <span ref={light} className="argus-path-light" aria-hidden />
        <ol>
          {BELOW_SEVENTY_FIVE.map((step, index) => (
            <li key={step.title} className={index === BELOW_SEVENTY_FIVE.length - 1 ? "is-last" : undefined}>
              <span>{String(index + 1).padStart(2, "0")}</span>
              <div>
                <h3>{step.title}</h3>
                <p>{step.body}</p>
              </div>
            </li>
          ))}
        </ol>
      </div>
      <Disclaimer tone="argus" />
    </section>
  );
}

function EclipsePhases() {
  const first = useRef<HTMLDivElement>(null);
  const second = useRef<HTMLDivElement>(null);

  useScrub((timeline) => {
    if (!first.current || !second.current) return;
    timeline.fromTo(
      first.current,
      { filter: "blur(0px)", opacity: 1, scale: 1 },
      { filter: "blur(16px)", opacity: 0, scale: 0.96, duration: 0.7, ease: "none" },
      0,
    );
    timeline.fromTo(
      second.current,
      { filter: "blur(16px)", opacity: 0, scale: 1.04 },
      { filter: "blur(0px)", opacity: 1, scale: 1, duration: 0.7, ease: "none" },
      0.3,
    );
  });

  return (
    <div className="argus-eclipse">
      <div className="argus-scrim argus-eclipse-copy">
        <h2 className="argus-h2">How it works.</h2>
        <h2 className="argus-h2 argus-h2-quiet">After you clear.</h2>
      </div>
      <div id="how" ref={first} className="argus-phase">
        <ol className="argus-steps">
          <li>Interview</li>
          <li>Score</li>
          <li>Get seen</li>
        </ol>
        <div className="argus-phase-grid">
          <article>
            <h3>Interview</h3>
            <p>About 15 questions from your CV. You answer them. It is free.</p>
          </article>
          <article>
            <h3>Score</h3>
            <p>The AI scores the round out of 100. 75% or more is a clear.</p>
          </article>
          <article>
            <h3>Get seen</h3>
            <p>A clear sends your CV to 3,000 HR recruiters. Under 75%, the path is counselling, a plan, then a retake.</p>
          </article>
        </div>
        <Disclaimer tone="argus" />
      </div>
      <div id="after-clear" ref={second} className="argus-phase">
        <ol className="argus-steps">
          <li>Pre-qualified</li>
          <li>CV sent</li>
          <li>Interview call</li>
        </ol>
        <div className="argus-phase-grid">
          <article>
            <h3>You are pre-qualified.</h3>
            <p>75% or more is the clear mark. Employers see people who already cleared.</p>
          </article>
          <article>
            <h3>Your CV is sent.</h3>
            <p>{CLAIM_RECRUITERS}</p>
          </article>
          <article>
            <h3>The call is more likely.</h3>
            <p>{CLAIM_INTERVIEW_CALL} The market still decides.</p>
          </article>
        </div>
        <Disclaimer tone="argus" />
      </div>
    </div>
  );
}

export function HomeEclipse() {
  return (
    <ScenePin scene="eclipse" anchor="right" className="argus-section argus-eclipse-section">
      <EclipsePhases />
    </ScenePin>
  );
}

export function HomeFloor() {
  const tilt = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const node = tilt.current;
    if (!node || prefersReducedMotion()) return;
    const tween = gsap.fromTo(
      node,
      { rotateX: 20 },
      {
        rotateX: 0,
        ease: "none",
        scrollTrigger: { trigger: node, start: "top 85%", end: "top 35%", scrub: 1 },
      },
    );
    return () => {
      tween.scrollTrigger?.kill();
      tween.kill();
    };
  }, []);

  return (
    <section id="for-employers" className="argus-section argus-floor">
      <div id="ai-recruiter" className="argus-scrim argus-floor-copy">
        <h2 className="argus-h2">Your AI Recruiter.</h2>
        <p className="argus-body">Tell it the role. Watch every stage on the floor. The floor below is sample data.</p>
        <div className="argus-row">
          <ArgusButton href="/employers/mission-control-demo">Watch the demo</ArgusButton>
          <ArgusButton href="/employers" variant="secondary">
            Learn more
          </ArgusButton>
        </div>
        <p className="argus-row">
          <span className="argus-badge">Sample data</span>
          <span className="argus-badge">Demo data</span>
        </p>
      </div>
      <div ref={tilt} className="argus-tilt">
        <MacFloor tone="dark" />
      </div>
    </section>
  );
}

function DaysBoard() {
  const num = useRef<HTMLParagraphElement>(null);
  const usual = useRef<HTMLUListElement>(null);
  const withUs = useRef<HTMLUListElement>(null);

  useScrub((timeline) => {
    timeline.eventCallback("onUpdate", () => {
      const p = timeline.progress();
      const value = Math.round(90 - p * 87);
      if (num.current) num.current.textContent = p > 0.92 ? "≈3" : String(value);
      usual.current?.querySelectorAll("li").forEach((item, index) => {
        item.classList.toggle("is-struck", p >= (index + 1) / USUALLY.length);
      });
      withUs.current?.querySelectorAll("li").forEach((item, index) => {
        item.classList.toggle("is-on", p >= index / WITH_US.length);
      });
    });
  });

  return (
    <div className="argus-days">
      <div className="argus-scrim">
        <h2 className="argus-h2">
          90 days <span aria-hidden>→</span> 3 days.
          <sup>1</sup>
        </h2>
        <p className="argus-body">Where those 90 days usually go, and what we take off your team.</p>
        <p ref={num} className="argus-days-num">
          90
        </p>
      </div>
      <div className="argus-days-cols">
        <div>
          <h3>Usually</h3>
          <p>90 days</p>
          <ul ref={usual}>
            {USUALLY.map((item) => (
              <li key={item}>{item}</li>
            ))}
          </ul>
        </div>
        <div>
          <h3>With BrowseJobs</h3>
          <p>About 3 days</p>
          <ul ref={withUs}>
            {WITH_US.map((item) => (
              <li key={item}>{item}</li>
            ))}
          </ul>
        </div>
      </div>
      <Disclaimer tone="argus" />
      <div className="argus-gets">
        {GETS.map((item, index) => (
          <article key={item.title} className="argus-card">
            <span className="argus-card-no">{String(index + 1).padStart(2, "0")}</span>
            <h3>{item.title}</h3>
            <p>{item.body}</p>
          </article>
        ))}
      </div>
      <ArgusButton href="/employers/enquire">Onboard with us for the future of hiring</ArgusButton>
    </div>
  );
}

export function HomeDays() {
  return (
    <ScenePin id="impact" className="argus-section argus-days-section">
      <DaysBoard />
    </ScenePin>
  );
}

export function HomeFaq() {
  return (
    <section id="faq" className="argus-section argus-faq-section">
      <div className="argus-scrim argus-faq-wrap">
        <h2 className="argus-h2">Questions.</h2>
        <ArgusFaq />
        <Disclaimer tone="argus" />
      </div>
    </section>
  );
}

export function HomeClose() {
  return (
    <>
      <section className="argus-section argus-close" data-scene="globe" data-anchor="right" data-pins="claims">
        <div className="argus-scrim">
          <h2 className="argus-h2">
            Start with{" "}
            <br /> the interview.
          </h2>
          <p className="argus-body">It&apos;s free.</p>
          <ArgusButton href="/#interview-start">Take your free AI interview</ArgusButton>
          <Disclaimer tone="argus" />
        </div>
      </section>
      <section className="argus-grid-bookend" data-scene="grid" aria-hidden />
    </>
  );
}

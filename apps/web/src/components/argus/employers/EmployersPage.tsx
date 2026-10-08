"use client";

import { useEffect, useRef, useState } from "react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { ArgusButton, ArgusMore } from "@/components/argus/ui";
import { SplitHeading } from "@/components/argus/SplitHeading";
import { HomeDays } from "@/components/argus/home/scenes";
import { EMPLOYER_FAQ, EMPLOYER_WAYS } from "@/content/employer-landing";
import { hideScene } from "@/lib/scene-bus";
import { isMobileViewport, prefersReducedMotion } from "@/lib/motion";

if (typeof window !== "undefined") gsap.registerPlugin(ScrollTrigger);

const STEPS = [
  {
    id: "talk",
    title: "Talk to it.",
    line: "Type the role, or say it. The sample floor starts from that line.",
    href: "/employers/how-it-works",
  },
  {
    id: "calls",
    title: "We call and screen.",
    line: "We call and screen shortlisted candidates. You see who was called, the outcome, and what they said.",
    href: "/employers/how-it-works",
  },
  {
    id: "interview",
    title: "They interview first.",
    line: "Candidates take a free AI interview. 75% or more counts as a clear.",
    href: "/how-it-works",
  },
  {
    id: "rounds",
    title: "L1, L2, and pre-BGV.",
    line: "We run L1 and L2 for you, then pre-BGV. You see the status on the floor.",
    href: "/employers/how-it-works",
  },
  {
    id: "offer",
    title: "You release the offer.",
    line: "Needs your approval. A person must always release the offer letter.",
    href: "/employers/faq",
  },
] as const;

const NAV = [
  { href: "#overview", label: "Overview", section: "overview" },
  { href: "/employers/how-it-works", label: "How it works", section: "" },
  { href: "/employers/mission-control-demo", label: "Demo", section: "" },
  { href: "/employers/faq", label: "FAQ", section: "" },
  { href: "#get-started", label: "Get started", section: "get-started" },
] as const;

const ROWS = ["Sample Asha Iyer", "Sample Rohan Mehta", "Sample Meera Nair"] as const;
const PHRASE = "Backend engineers, Hyderabad.";
const SHORT_FAQ = EMPLOYER_FAQ.filter((_, index) => index === 0 || index === 1 || index === 5);

export function EmployersPage() {
  const [active, setActive] = useState("overview");

  useEffect(() => {
    const ids = ["overview", "impact", "floor", "faq", "get-started"];
    const nodes = ids.map((id) => document.getElementById(id)).filter((node): node is HTMLElement => !!node);
    const observer = new IntersectionObserver(
      (entries) => {
        const best = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
        if (best?.target.id) setActive(best.target.id);
      },
      { rootMargin: "-35% 0px -45% 0px", threshold: [0.15, 0.4, 0.7] },
    );
    nodes.forEach((node) => observer.observe(node));
    return () => observer.disconnect();
  }, []);

  return (
    <>
      <nav className="argus-subnav" aria-label="BrowseJobs AI Recruiter">
        <p className="argus-product">
          BrowseJobs AI Recruiter
          <small>Powered by Taurus AI</small>
        </p>
        {NAV.map((item) => (
          <a key={item.label} href={item.href} aria-current={item.section && item.section === active ? "true" : undefined}>
            {item.label}
          </a>
        ))}
      </nav>

      <section id="overview" className="argus-section argus-hero argus-employer-hero" data-scene="eclipse" data-anchor="aside">
        <div className="argus-employer-copy">
          <p className="argus-kicker">For employers</p>
          <SplitHeading as="h1" className="argus-h1" text="Your AI Recruiter." breakAfter={1} />
          <p className="argus-body argus-hero-sub">
            From <span className="argus-violet">90</span> days to <span className="argus-violet">3</span> days.
            <sup>1</sup> Pre-qualified candidates. You still release the offer.
          </p>
          <Disclaimer tone="argus" />
          <div className="argus-row">
            <ArgusButton href="#get-started">Get started</ArgusButton>
            <ArgusMore href="/employers/mission-control-demo">Watch the demo of the hiring floor</ArgusMore>
          </div>
        </div>
      </section>

      <HomeDays checks />
      <FloorStory />

      <section id="faq" className="argus-section argus-faq-section">
        <div className="argus-scrim argus-faq-wrap">
          <SplitHeading as="h2" className="argus-h2" text="A few answers." />
          <div className="argus-faq">
            {SHORT_FAQ.map((item) => (
              <details key={item.q} className="argus-faq-item">
                <summary>
                  {item.q}
                  <span aria-hidden>+</span>
                </summary>
                <p>{item.a}</p>
              </details>
            ))}
          </div>
          <ArgusMore href="/employers/faq">Learn more in the full FAQ</ArgusMore>
        </div>
      </section>

      <section id="get-started" className="argus-section argus-onboard" data-scene="globe" data-anchor="aside" data-pins="none">
        <div className="argus-onboard-copy">
          <SplitHeading as="h2" className="argus-h2" text="Onboard with us." />
          <p className="argus-body">We can run hiring with you, or your team can use the tool.</p>
          <ArgusButton href="/employers/enquire">Onboard with us for the future of hiring</ArgusButton>
          <div className="argus-way-grid">
            {EMPLOYER_WAYS.map((way) => (
              <a key={way.id} className="argus-card argus-way" href={way.href}>
                <h3>{way.cta}</h3>
                <p>{way.body}</p>
              </a>
            ))}
          </div>
        </div>
      </section>
    </>
  );
}

function FloorStory() {
  const root = useRef<HTMLElement>(null);
  const stage = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const section = root.current;
    const board = stage.current;
    if (!section || !board || prefersReducedMotion() || isMobileViewport()) return;
    const steps = [...board.querySelectorAll<HTMLElement>("[data-step]")];
    const panes = [...board.querySelectorAll<HTMLElement>("[data-pane]")];
    const dots = [...board.querySelectorAll<HTMLElement>("[data-dot]")];
    const paint = (progress: number, active: boolean) => {
      const scaled = Math.min(0.999, Math.max(0, progress)) * STEPS.length;
      const index = Math.min(STEPS.length - 1, Math.floor(scaled));
      const fraction = scaled - index;
      steps.forEach((step, stepIndex) => step.classList.toggle("is-on", stepIndex === index));
      panes.forEach((pane, stepIndex) => pane.classList.toggle("is-on", stepIndex === index));
      dots.forEach((dot, stepIndex) => dot.classList.toggle("is-on", stepIndex <= index));
      paintDevice(board, index, fraction);
      if (active) hideScene();
    };
    paint(0, false);
    const trigger = ScrollTrigger.create({
      trigger: section,
      start: "top top",
      end: "+=500%",
      pin: board,
      scrub: 1,
      onUpdate: (self) => paint(self.progress, self.isActive),
    });
    return () => trigger.kill();
  }, []);

  return (
    <section id="floor" ref={root} className="argus-floor-story">
      <div ref={stage} className="argus-floor-stage">
        <ol className="argus-rail" aria-hidden>
          {STEPS.map((step, index) => (
            <li key={step.id} data-dot={index} className={index === 0 ? "is-on" : undefined} />
          ))}
        </ol>
        <div className="argus-step-stack">
          {STEPS.map((step, index) => (
            <article key={step.id} id={step.id} data-step={index} className={index === 0 ? "argus-step is-on" : "argus-step"}>
              <h2 className="argus-h2">{step.title}</h2>
              <p className="argus-body">{step.line}</p>
              {step.id === "interview" ? <Disclaimer tone="argus" /> : null}
              <ArgusMore href={step.href}>Learn more</ArgusMore>
              <div className="argus-device is-inline">
                <DeviceFace step={index} live={false} />
              </div>
            </article>
          ))}
        </div>
        <div className="argus-device is-shared" data-device>
          {STEPS.map((step, index) => (
            <div key={step.id} data-pane={index} className={index === 0 ? "argus-device-pane is-on" : "argus-device-pane"}>
              <DeviceFace step={index} live />
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function paintDevice(board: HTMLElement, index: number, fraction: number) {
  const talk = board.querySelector<HTMLElement>("[data-talk]");
  if (talk) talk.textContent = index === 0 ? PHRASE.slice(0, Math.max(1, Math.ceil(fraction * PHRASE.length))) : PHRASE;
  board.querySelectorAll<HTMLElement>("[data-chip]").forEach((chip, chipIndex) => {
    const done = index > 1 || (index === 1 && fraction > (chipIndex + 1) / (ROWS.length + 1));
    const last = chipIndex === ROWS.length - 1;
    chip.textContent = done && !last ? "Screened ✓" : "Calling…";
    chip.classList.toggle("is-done", done && !last);
  });
  const fill = index > 2 ? 75 : index === 2 ? Math.round(Math.min(0.75, fraction * 0.75) * 100) : 0;
  board.querySelectorAll<HTMLElement>("[data-score]").forEach((node) => node.style.setProperty("--p", String(fill)));
  const clear = board.querySelector<HTMLElement>("[data-clear]");
  if (clear) clear.classList.toggle("is-on", fill >= 74);
  board.querySelectorAll<HTMLElement>("[data-round]").forEach((node, roundIndex) => {
    const on = index > 3 || (index === 3 && fraction > (roundIndex + 1) / 4);
    node.classList.toggle("is-on", on);
  });
}

function DeviceFace({ step, live }: { step: number; live: boolean }) {
  return (
    <div className="argus-device-face">
      <p className="argus-device-badges">
        <span className="argus-badge">Sample data</span>
        <span className="argus-badge">Demo data</span>
      </p>
      {step === 0 ? (
        <div className="argus-prompt">
          <span data-talk={live ? "live" : undefined}>{PHRASE}</span>
          <i className="argus-mic" aria-hidden />
        </div>
      ) : null}
      {step === 1 ? (
        <ul className="argus-call-list">
          {ROWS.map((name, index) => (
            <li key={name}>
              <span>{name}</span>
              <em data-chip={live ? index : undefined} className={index < 2 ? "is-done" : undefined}>
                {index < 2 ? "Screened ✓" : "Calling…"}
              </em>
            </li>
          ))}
        </ul>
      ) : null}
      {step === 2 ? (
        <div className="argus-score-mock">
          <div className="argus-score-dial" data-score={live ? "live" : undefined} style={{ ["--p" as string]: 75 }}>
            <span>75%</span>
          </div>
          <p data-clear={live ? "live" : undefined} className="argus-clear-chip is-on">
            Clear
          </p>
        </div>
      ) : null}
      {step === 3 ? (
        <ol className="argus-stepper">
          {["L1", "L2", "Pre-BGV"].map((label, index) => (
            <li key={label} data-round={live ? index : undefined} className="is-on">
              <span>{index + 1}</span>
              {label}
            </li>
          ))}
        </ol>
      ) : null}
      {step === 4 ? (
        <div className="argus-offer-card">
          <p>Offer letter</p>
          <strong>Waiting</strong>
          <p>A person releases it. Nothing is emailed automatically.</p>
          <button type="button" className="argus-approve">
            Approve
          </button>
        </div>
      ) : null}
    </div>
  );
}

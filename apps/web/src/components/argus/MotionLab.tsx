"use client";

import { useRef } from "react";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { CountUp } from "./CountUp";
import { Reveal } from "./Reveal";
import { ScenePin, SceneTransition, useScrub } from "./ScenePin";
import { SplitHeading } from "./SplitHeading";
import { ArgusBadge, ArgusFaq, ArgusFields, ArgusMore, GlassCard } from "./ui";

function ScrubFill() {
  const bar = useRef<HTMLDivElement>(null);
  useScrub((timeline) => {
    if (!bar.current) return;
    timeline.fromTo(bar.current, { scaleX: 0 }, { scaleX: 1, duration: 1, ease: "none" }, 0);
  });
  return (
    <div style={{ marginTop: "1.5rem", height: 2, width: "min(420px, 80vw)", background: "#e5e5ea" }}>
      <div ref={bar} style={{ height: "100%", width: "100%", transformOrigin: "left center", background: "var(--violet-200)" }} />
    </div>
  );
}

export function MotionLab() {
  return (
    <>
      <section id="objects" className="argus-section" data-scene="ring" data-shot="object">
        <div className="argus-section-copy">
          <p className="argus-kicker">Object 01 · Horizon ring</p>
          <SplitHeading as="h1" className="argus-h1" text="Take a free AI interview." />
          <p className="argus-body" style={{ marginTop: "1rem" }}>
            The arc draws itself on as the section enters. Text stays in the page, not on the canvas.
          </p>
        </div>
      </section>

      <ScenePin scene="score" shot="object" className="argus-section">
        <div className="argus-section-copy">
          <p className="argus-kicker">Object 02 · Score ring</p>
          <h2 className="argus-h2">
            <CountUp to={75} suffix="%" />
          </h2>
          <p className="argus-clear">75% clear</p>
          <p className="argus-body" style={{ marginTop: "0.8rem" }}>
            Pinned on desktop. The ring fills with the scrub. On a phone this section does not pin.
          </p>
          <ScrubFill />
          <Disclaimer tone="argus" />
        </div>
      </ScenePin>

      <section className="argus-section" data-scene="sphere" data-shot="object">
        <div className="argus-section-copy">
          <p className="argus-kicker">Object 03 · Glass sphere</p>
          <SplitHeading className="argus-h2" text="A comet crosses the glass." />
          <p className="argus-body" style={{ marginTop: "1rem" }}>
            A light fresnel rim and a small mark orbiting the surface. The shell stays off the copy.
          </p>
        </div>
      </section>

      <section className="argus-section" data-scene="eclipse" data-shot="object">
        <div className="argus-section-copy">
          <p className="argus-kicker">Object 04 · Eclipse</p>
          <SplitHeading className="argus-h2" text="The light breaks into a crescent." />
          <p className="argus-body" style={{ marginTop: "1rem" }}>A dark sphere sits in the empty side of the section. It does not travel across the copy.</p>
        </div>
      </section>

      <section className="argus-section" data-scene="globe" data-shot="object">
        <div className="argus-section-copy">
          <p className="argus-kicker">Object 05 · Dot globe</p>
          <SplitHeading className="argus-h2" text="Pins on a turning globe." />
          <p className="argus-body" style={{ marginTop: "1rem" }}>
            About 20,000 points sampled from the world map, with connector lines to the city labels.
          </p>
        </div>
      </section>

      <section className="argus-section" data-scene="grid" data-shot="object">
        <div className="argus-section-copy">
          <p className="argus-kicker">Object 06 · Grid floor</p>
          <SplitHeading className="argus-h2" text="A light grid from the horizon." />
          <p className="argus-body" style={{ marginTop: "1rem" }}>The floor moves toward you. It is the ground under later forms.</p>
        </div>
      </section>

      <SceneTransition
        from={
          <div className="argus-section-copy">
            <p className="argus-kicker">Transition · out</p>
            <h2 className="argus-h2">90 days</h2>
            <p className="argus-body" style={{ marginTop: "1rem" }}>The outgoing panel blurs, fades, and scales down.</p>
          </div>
        }
        to={
          <div className="argus-section-copy">
            <p className="argus-kicker">Transition · in</p>
            <h2 className="argus-h2">3 days</h2>
            <p className="argus-body" style={{ marginTop: "1rem" }}>The next panel arrives through the same blur, overlapping the exit.</p>
          </div>
        }
      />

      <section id="components" className="argus-section" style={{ alignItems: "flex-start" }}>
        <div style={{ width: "min(1100px, 100%)", margin: "0 auto" }}>
          <Reveal
            heading={<h2 className="argus-h2">Components.</h2>}
            body={<p className="argus-body">Cards, counts, badges, and the questions already on the site.</p>}
          >
            <div style={{ display: "flex", flexWrap: "wrap", gap: "0.6rem", margin: "1.25rem 0" }}>
              <ArgusBadge>Sample data</ArgusBadge>
              <ArgusBadge>Demo data</ArgusBadge>
              <span className="argus-clear">Applied ✓</span>
            </div>
            <div style={{ display: "flex", flexWrap: "wrap", gap: "1.5rem 2.5rem", marginBottom: "0.4rem" }}>
              <p className="argus-h2"><CountUp to={4.9} decimals={1} /></p>
              <p className="argus-h2"><CountUp to={473} /></p>
              <p className="argus-h2"><CountUp to={3000} /></p>
              <p className="argus-h2"><CountUp from={90} to={3} /></p>
            </div>
            <Disclaimer tone="argus" />
            <div style={{ display: "grid", gap: "1rem", marginTop: "1.5rem" }} className="argus-card-grid">
              <GlassCard index="01" title="Score 75% and your CV is sent to 3,000 HR recruiters.">
                Clearing the AI interview (75% or more) increases your chance of getting an interview call by almost 60%.
              </GlassCard>
              <GlassCard index="02" title="A person always releases the offer.">
                The floor on these pages is sample data. Nothing there is emailed on its own.
              </GlassCard>
            </div>
            <div style={{ marginTop: "1rem" }}>
              <ArgusMore href="/students">Students</ArgusMore>
            </div>
            <div style={{ marginTop: "2rem", maxWidth: 760 }}>
              <ArgusFaq />
            </div>
          </Reveal>
        </div>
      </section>

      <section id="controls" className="argus-section" data-scene="grid">
        <div style={{ width: "min(720px, 100%)", margin: "0 auto" }}>
          <p className="argus-kicker" id="navbar">Navbar, buttons, inputs</p>
          <h2 className="argus-h2">Buttons and inputs.</h2>
          <p className="argus-body" style={{ margin: "1rem 0 1.25rem" }}>
            The navbar stays fixed above this page. These fields do not submit.
          </p>
          <ArgusFields />
        </div>
      </section>
    </>
  );
}

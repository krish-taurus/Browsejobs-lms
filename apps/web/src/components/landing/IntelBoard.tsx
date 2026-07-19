"use client";

import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { Kicker } from "@/components/brand/Kicker";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { TiltCard } from "@/components/motion/TiltCard";
import { LOOP, type OutlookSeries } from "@/content/intel-board";
import { useMarketIntel } from "@/lib/market-intel";

/**
 * Intelligence Board — the instrument panel under Market Pulse: funding →
 * hiring signals, forward demand curves (drawn-in SVG, dashed projection tail),
 * and the loop that turns signal into offers. Sector-level and illustrative
 * (spec §3: no fabricated company claims); shared Disclaimer included.
 */

function Sparkline({ series }: { series: OutlookSeries }) {
  const reduce = useReducedMotion();
  const W = 280;
  const H = 72;
  const n = series.points.length;
  const solidCount = n - 3; // last 3 points are the projection
  const pt = (v: number, i: number) =>
    `${(i / (n - 1)) * W},${H - (v / 100) * (H - 8) - 4}`;
  const solid = series.points.slice(0, solidCount + 1).map(pt).join(" ");
  const proj = series.points.slice(solidCount).map((v, i) => pt(v, solidCount + i)).join(" ");

  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="h-16 w-full" aria-hidden>
      <motion.polyline
        points={solid}
        fill="none"
        stroke="var(--bj-trust)"
        strokeWidth="2"
        strokeLinecap="round"
        initial={reduce ? undefined : { pathLength: 0 }}
        whileInView={reduce ? undefined : { pathLength: 1 }}
        viewport={{ once: true, margin: "-60px" }}
        transition={{ duration: durations.slower, ease }}
      />
      <motion.polyline
        points={proj}
        fill="none"
        stroke="var(--bj-trust)"
        strokeWidth="2"
        strokeDasharray="3 5"
        strokeLinecap="round"
        opacity="0.55"
        initial={reduce ? undefined : { pathLength: 0 }}
        whileInView={reduce ? undefined : { pathLength: 1 }}
        viewport={{ once: true, margin: "-60px" }}
        transition={{ duration: durations.slow, ease, delay: durations.slower }}
      />
    </svg>
  );
}

export function IntelBoard() {
  const { funding, outlook, live, effectiveOn } = useMarketIntel();

  return (
    <section id="intel" className="mx-auto max-w-6xl px-5 py-16 md:py-24">
      <ScrollReveal>
        <Kicker>The intelligence board</Kicker>
        <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-5xl">
          Capital moves first. We train you into where it lands.
        </h2>
        <p className="mt-4 max-w-2xl text-ink2/70">
          Funding rounds and expansion news usually turn into hiring a few months
          later. The engine reads those public signals, projects where demand
          goes next, and rebuilds the syllabus so you arrive prepared — before
          the crowd does.
        </p>
      </ScrollReveal>

      <div className="mt-12 grid gap-5 lg:grid-cols-2">
        {/* Funding → hiring signal */}
        <ScrollReveal className="h-full">
          <TiltCard className="h-full">
            <div className="flex h-full flex-col rounded-[22px] border border-line bg-white p-7 shadow-soft">
              <div className="flex items-center justify-between">
                <span className="mono text-[10px] font-semibold uppercase tracking-[0.18em] text-trust">
                  Funding → hiring signal
                </span>
                <span className="mono text-[10px] text-muted">{live && effectiveOn ? `updated ${effectiveOn}` : "sector-level · public news"}</span>
              </div>
              <ul className="mt-5 flex-1 space-y-4">
                {funding.map((f) => (
                  <li key={f.sector} className="border-t border-line pt-4 first:border-t-0 first:pt-0">
                    <div className="flex items-baseline justify-between gap-3">
                      <span className="font-semibold text-ink">{f.sector}</span>
                      <span className="mono text-xs text-muted">{f.hub}</span>
                    </div>
                    <div className="mt-1 flex items-center justify-between gap-3">
                      <span className="text-sm text-ink2/70">{f.stage}</span>
                      <span className="mono whitespace-nowrap text-xs text-trust">
                        hiring in ~{f.hiringLagMonths} mo
                      </span>
                    </div>
                    {/* Lag bar — the raise→ramp window drawn to scale. */}
                    <div className="mt-2 h-1 overflow-hidden rounded-full bg-sky">
                      <motion.div
                        className="h-full rounded-full bg-trust"
                        initial={{ scaleX: 0 }}
                        whileInView={{ scaleX: f.hiringLagMonths / 6 }}
                        viewport={{ once: true, margin: "-60px" }}
                        style={{ transformOrigin: "left" }}
                        transition={{ duration: durations.slower, ease }}
                      />
                    </div>
                    <p className="mono mt-2 text-[11px] text-muted">
                      {f.roles.join(" · ")}
                    </p>
                  </li>
                ))}
              </ul>
            </div>
          </TiltCard>
        </ScrollReveal>

        {/* Demand outlook */}
        <ScrollReveal delay={0.08} className="h-full">
          <TiltCard className="h-full">
            <div className="flex h-full flex-col rounded-[22px] border border-line bg-white p-7 shadow-soft">
              <div className="flex items-center justify-between">
                <span className="mono text-[10px] font-semibold uppercase tracking-[0.18em] text-trust">
                  Demand outlook · next 2 quarters
                </span>
                <span className="mono text-[10px] text-muted">dashed = projection</span>
              </div>
              <ul className="mt-5 flex-1 space-y-5">
                {outlook.map((s) => (
                  <li key={s.track} className="border-t border-line pt-4 first:border-t-0 first:pt-0">
                    <div className="flex items-baseline justify-between">
                      <span className="font-semibold text-ink">{s.track}</span>
                      <span className={`mono text-xs ${s.direction === "rising" ? "text-trust" : "text-muted"}`}>
                        {s.direction === "rising" ? "rising ↑" : "steady →"}
                      </span>
                    </div>
                    <Sparkline series={s} />
                  </li>
                ))}
              </ul>
            </div>
          </TiltCard>
        </ScrollReveal>
      </div>

      {/* Signal → offer loop */}
      <ScrollReveal delay={0.1}>
        <div className="mt-5 grid gap-5 rounded-[22px] border border-line bg-white p-7 shadow-soft md:grid-cols-4">
          {LOOP.map((l, i) => (
            <div key={l.step} className="relative">
              <span className="mono text-[10px] font-semibold uppercase tracking-[0.18em] text-trust">
                {String(i + 1).padStart(2, "0")} · {l.step}
              </span>
              <h3 className="mt-2 font-semibold text-ink">{l.title}</h3>
              <p className="mt-1.5 text-sm leading-relaxed text-ink2/70">{l.body}</p>
            </div>
          ))}
        </div>
      </ScrollReveal>

      <ScrollReveal delay={0.12}>
        <p className="mono mt-4 text-[11px] text-muted">
          Illustrative board — sector-level directions compiled from public funding and
          hiring news on a scheduled refresh. Signals, not guarantees.
        </p>
        <Disclaimer className="mt-1" />
      </ScrollReveal>
    </section>
  );
}

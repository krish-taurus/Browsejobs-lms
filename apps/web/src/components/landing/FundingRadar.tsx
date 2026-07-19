"use client";

import { Kicker } from "@/components/brand/Kicker";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { useMarketIntel } from "@/lib/market-intel";

/**
 * Funding Radar — the "from our research" mini-hero: the freshest sourced
 * funding/expansion story, told as where hiring lands next (roles, skills,
 * window), with a read-the-source link whenever a public source URL exists.
 * A named company only ever appears with its source (Spec §3 honesty rule —
 * enforced server-side in the curation API).
 */
export function FundingRadar() {
  const { radar, live } = useMarketIntel();
  if (radar.length === 0) return null;
  const [top, ...rest] = radar;

  return (
    <section aria-label="Funding radar — where hiring lands next" className="mx-auto max-w-6xl px-5 pt-16 md:pt-24">
      <ScrollReveal>
        <div className="grain relative overflow-hidden rounded-[22px] bg-ink p-7 text-white shadow-soft md:p-10">
          <div
            aria-hidden
            className="absolute inset-0 bg-cover bg-center opacity-50"
            style={{ backgroundImage: "url(/art/engine-aurora.svg)" }}
          />
          <div className="relative">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <Kicker tone="sky">Funding radar · from our research</Kicker>
              <span className="mono text-[10px] uppercase tracking-[0.18em] text-sky/50">
                {live ? "refreshed daily" : "sample — goes live with curation"}
              </span>
            </div>

            {/* The featured story. */}
            <div className="mt-5 md:flex md:items-end md:justify-between md:gap-8">
              <div>
                <h2 className="display text-2xl md:text-4xl">
                  {top.company ?? top.sector} · {top.round}
                </h2>
                <p className="mt-3 max-w-xl text-sky/75">
                  {top.company ? `${top.company} (${top.sector})` : `A ${top.sector.toLowerCase()} move`} in{" "}
                  <span className="text-white">{top.hub}</span> — our engine expects the hiring wave in{" "}
                  <span className="mono text-white">~{top.hiringLagMonths} months</span>, led by{" "}
                  <span className="text-white">{top.roles.join(" and ")}</span> roles.
                </p>
                <div className="mt-4 flex flex-wrap items-center gap-1.5">
                  <span className="mono text-[10px] uppercase tracking-[0.18em] text-sky/50">In-demand skills</span>
                  {top.skills.map((sk) => (
                    <span key={sk} className="mono rounded-full border border-white/15 bg-white/5 px-2.5 py-1 text-[11px] text-sky/90">
                      {sk}
                    </span>
                  ))}
                </div>
              </div>
              <div className="mt-5 shrink-0 md:mt-0 md:text-right">
                {top.sourceUrl ? (
                  <a
                    href={top.sourceUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1.5 rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition-colors hover:border-white/60"
                  >
                    Read the source ↗
                  </a>
                ) : null}
                <p className="mono mt-2 text-[10px] text-sky/50">
                  {top.sourceName}
                  {top.publishedOn ? ` · ${top.publishedOn}` : ""}
                </p>
              </div>
            </div>

            {/* The rest of the radar, compact. */}
            {rest.length > 0 && (
              <div className="mt-6 grid gap-3 border-t border-white/10 pt-5 md:grid-cols-2">
                {rest.slice(0, 4).map((r) => (
                  <div key={`${r.sector}-${r.round}-${r.hub}`} className="flex items-baseline justify-between gap-3 text-sm">
                    <span className="text-sky/85">
                      <span className="font-semibold text-white">{r.company ?? r.sector}</span> · {r.round} · {r.hub}
                    </span>
                    <span className="mono shrink-0 text-xs text-sky/60">
                      {r.sourceUrl ? (
                        <a href={r.sourceUrl} target="_blank" rel="noopener noreferrer" className="hover:text-white">
                          source ↗
                        </a>
                      ) : (
                        `~${r.hiringLagMonths} mo`
                      )}
                    </span>
                  </div>
                ))}
              </div>
            )}

            <p className="mono mt-5 text-[10px] leading-relaxed text-sky/40">
              Compiled from public funding &amp; hiring news. Signals, not guarantees — every
              named company links to its public source.
            </p>
          </div>
        </div>
      </ScrollReveal>
    </section>
  );
}

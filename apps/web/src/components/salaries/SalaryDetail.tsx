"use client";

import { useState } from "react";
import Link from "next/link";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease, stagger } from "@/lib/motion";
import { Kicker } from "@/components/brand/Kicker";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { MaskReveal } from "@/components/motion/MaskReveal";
import { MonoCounter } from "@/components/motion/MonoCounter";
import { DecodeText } from "@/components/motion/DecodeText";
import { Spotlight } from "@/components/motion/Spotlight";
import { TiltCard } from "@/components/motion/TiltCard";
import { BookCta } from "@/components/landing/BookCta";
import { sameRolePages, type SalaryBand, type SalaryPage } from "@/content/salaries";

/**
 * The cinematic body of a /salaries page: aurora ink hero with a decoded
 * headline and a huge counted median, an animated percentile band (markers
 * slide into place along a drawn track), band toggle, tilting city-comparison
 * cards and the skills row. All motion uses the shared tokens, transform/
 * opacity only, and every figure sits above the mandatory Disclaimer.
 */

const BAND_LABEL: Record<string, string> = { fresher: "Fresher", "1-3": "1–3 yrs experience", "3-5": "3–5 yrs" };

function PercentileTrack({ band }: { band: SalaryBand }) {
  const reduce = useReducedMotion();
  const max = band.p75 * 1.15;
  const pct = (v: number) => (v / max) * 100;

  return (
    <div className="mt-8">
      <div className="relative h-2 overflow-hidden rounded-full bg-white/10">
        <motion.div
          className="absolute inset-y-0 left-0 rounded-full"
          style={{
            width: `${pct(band.p75)}%`,
            background: "linear-gradient(90deg, rgba(27,109,240,0.25), rgba(27,109,240,0.9))",
            transformOrigin: "left",
          }}
          initial={reduce ? undefined : { scaleX: 0 }}
          whileInView={reduce ? undefined : { scaleX: 1 }}
          viewport={{ once: true }}
          transition={{ duration: durations.slower, ease }}
        />
      </div>
      <div className="relative mt-3 h-14">
        {([
          ["p25", band.p25, "entry offers"],
          ["p50", band.p50, "median"],
          ["p75", band.p75, "strong offers"],
        ] as const).map(([key, value, label], i) => (
          <motion.div
            key={key}
            className="absolute -translate-x-1/2 text-center"
            style={{ left: `${pct(value)}%` }}
            initial={reduce ? undefined : { opacity: 0, y: 12 }}
            whileInView={reduce ? undefined : { opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: durations.slow, ease, delay: durations.slower + i * stagger }}
          >
            <span className={`mono block text-sm font-semibold ${key === "p50" ? "text-white" : "text-sky/70"}`}>
              ₹{value} LPA
            </span>
            <span className="mono text-[9px] uppercase tracking-[0.16em] text-sky/45">{label}</span>
          </motion.div>
        ))}
      </div>
    </div>
  );
}

export function SalaryDetail({ page }: { page: SalaryPage }) {
  const [bandIdx, setBandIdx] = useState(page.bands.length > 1 ? 1 : 0);
  const band = page.bands[bandIdx];
  const siblings = sameRolePages(page);

  return (
    <>
      {/* Cinematic ink hero */}
      <section className="relative overflow-hidden">
        <Spotlight
          className="grain bg-ink text-white"
          backdrop={
            <div
              aria-hidden
              className="absolute inset-0 bg-cover bg-center opacity-60"
              style={{ backgroundImage: "url(/art/engine-aurora.svg)" }}
            />
          }
        >
          <div className="mx-auto max-w-6xl px-5 pb-16 pt-14 md:pb-24 md:pt-20">
            <Kicker tone="sky">Salary intelligence · {page.city}</Kicker>
            <h1 className="display mt-4 text-[clamp(2.2rem,6vw,4.25rem)] leading-[1.05]">
              <MaskReveal index={0}>{page.role}</MaskReveal>
              <MaskReveal index={1} className="text-trust">
                <DecodeText text={`in ${page.city}`} className="mono" />
              </MaskReveal>
            </h1>
            <p className="mt-5 max-w-xl text-sky/75">{page.blurb}</p>

            <div className="mt-10 flex flex-wrap items-end gap-8">
              <div>
                <p className="mono text-[10px] uppercase tracking-[0.18em] text-sky/50">
                  Median · {BAND_LABEL[band.band] ?? band.band}
                </p>
                <p className="mono mt-1 text-6xl font-semibold text-white md:text-7xl">
                  ₹<MonoCounter value={band.p50} decimals={1} plain className="inline" />
                  <span className="ml-2 text-2xl text-sky/60">LPA</span>
                </p>
              </div>
              {page.bands.length > 1 && (
                <div className="flex gap-1.5 pb-2">
                  {page.bands.map((b, i) => (
                    <button
                      key={b.band}
                      type="button"
                      onClick={() => setBandIdx(i)}
                      className={`rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors ${
                        i === bandIdx ? "bg-trust text-white" : "border border-white/20 text-sky/70 hover:border-white/50"
                      }`}
                    >
                      {BAND_LABEL[b.band] ?? b.band}
                    </button>
                  ))}
                </div>
              )}
            </div>

            <PercentileTrack key={band.band} band={band} />
            <Disclaimer className="mt-6 !text-sky/40" />
          </div>
        </Spotlight>
      </section>

      {/* Skills the market pays this for */}
      <section className="mx-auto max-w-6xl px-5 py-14 md:py-20">
        <ScrollReveal>
          <Kicker>What earns it</Kicker>
          <h2 className="display mt-3 text-2xl text-ink md:text-4xl">
            The skills behind these offers
          </h2>
        </ScrollReveal>
        <ScrollReveal delay={0.08}>
          <div className="mt-6 flex flex-wrap gap-2">
            {page.skills.map((sk, i) => (
              <motion.span
                key={sk}
                className="mono rounded-full border border-line bg-white px-4 py-2 text-sm text-ink shadow-soft"
                initial={{ opacity: 0, y: 14 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: "-60px" }}
                transition={{ duration: durations.slow, ease, delay: i * stagger }}
              >
                {sk}
              </motion.span>
            ))}
          </div>
          {page.track && (
            <p className="mt-5 text-ink2/70">
              We teach every one of these in the{" "}
              <Link href={page.track.href} className="font-semibold text-trust hover:underline">
                {page.track.name} track
              </Link>{" "}
              — rebuilt monthly from real interviews.
            </p>
          )}
        </ScrollReveal>
      </section>

      {/* Same role, other cities */}
      {siblings.length > 0 && (
        <section className="mx-auto max-w-6xl px-5 pb-14 md:pb-20">
          <ScrollReveal>
            <Kicker>Compare cities</Kicker>
            <h2 className="display mt-3 text-2xl text-ink md:text-4xl">
              {page.role} pay across India
            </h2>
          </ScrollReveal>
          <div className="mt-8 grid gap-5 md:grid-cols-3">
            {siblings.map((s, i) => {
              const b = s.bands[s.bands.length - 1];
              return (
                <ScrollReveal key={s.slug} delay={i * 0.06} className="h-full">
                  <TiltCard className="h-full">
                    <Link
                      href={`/salaries/${s.slug}`}
                      className="group flex h-full flex-col rounded-[14px] border border-line bg-white p-6 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:border-trust/40"
                    >
                      <span className="mono text-[10px] uppercase tracking-[0.18em] text-muted">{s.city}</span>
                      <span className="mono mt-2 text-3xl font-semibold text-ink">₹{b.p50} LPA</span>
                      <span className="mono mt-1 text-xs text-muted">median · {BAND_LABEL[b.band] ?? b.band}</span>
                      <span className="mt-4 flex items-center gap-1.5 text-sm font-semibold text-trust">
                        View breakdown
                        <span className="transition-transform duration-300 group-hover:translate-x-1">→</span>
                      </span>
                    </Link>
                  </TiltCard>
                </ScrollReveal>
              );
            })}
          </div>
          <Disclaimer className="mt-6" />
        </section>
      )}

      {/* CTA band */}
      <section className="mx-auto max-w-6xl px-5 pb-16 md:pb-24">
        <ScrollReveal>
          <Spotlight
            className="grain rounded-[22px] bg-ink px-8 py-14 text-center text-white shadow-soft"
            backdrop={
              <div
                aria-hidden
                className="absolute inset-0 bg-cover bg-center opacity-50"
                style={{ backgroundImage: "url(/art/engine-aurora.svg)" }}
              />
            }
          >
            <p className="kicker text-sky/70">Free, before any fee</p>
            <h2 className="display mx-auto mt-4 max-w-2xl text-3xl md:text-4xl">
              See how we train people into these offers
            </h2>
            <p className="mx-auto mt-3 max-w-lg text-sky/70">
              A live masterclass on the engine that rebuilds our syllabus from
              real {page.city} interviews — every month.
            </p>
            <div className="mt-7">
              <BookCta className="px-8 py-3.5" />
            </div>
          </Spotlight>
        </ScrollReveal>
      </section>
    </>
  );
}

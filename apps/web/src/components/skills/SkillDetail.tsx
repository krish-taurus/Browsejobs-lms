"use client";

import Link from "next/link";
import { motion } from "framer-motion";
import { durations, ease, stagger } from "@/lib/motion";
import { Kicker } from "@/components/brand/Kicker";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { MaskReveal } from "@/components/motion/MaskReveal";
import { DecodeText } from "@/components/motion/DecodeText";
import { Spotlight } from "@/components/motion/Spotlight";
import { TiltCard } from "@/components/motion/TiltCard";
import { BookCta } from "@/components/landing/BookCta";
import { getSkillPage, type SkillPage } from "@/content/skills";

/**
 * /skills page body — same cinematic system as the salary pages: aurora ink
 * hero with decoded skill name, staggered "what interviews ask" cards,
 * city chips, role links into the salary pages, and the masterclass close.
 */
export function SkillDetail({ page }: { page: SkillPage }) {
  return (
    <>
      <section className="relative overflow-hidden">
        <Spotlight
          className="grain bg-ink text-white"
          backdrop={
            <div aria-hidden className="absolute inset-0 bg-cover bg-center opacity-55" style={{ backgroundImage: "url(/art/engine-aurora.svg)" }} />
          }
        >
          <div className="mx-auto max-w-6xl px-5 pb-14 pt-14 md:pb-20 md:pt-20">
            <Kicker tone="sky">Skill intelligence</Kicker>
            <h1 className="display mt-4 text-[clamp(2.2rem,6vw,4.25rem)] leading-[1.05]">
              <MaskReveal index={0}>
                <DecodeText text={page.name} className="mono" />
              </MaskReveal>
              <MaskReveal index={1} className="text-trust">
                {page.direction === "rising" ? "demand rising ↑" : "demand steady →"}
              </MaskReveal>
            </h1>
            <p className="mt-5 max-w-xl text-sky/75">{page.blurb}</p>
            <div className="mt-6 flex flex-wrap gap-1.5">
              {page.cities.map((c) => (
                <span key={c} className="mono rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[11px] text-sky/85">
                  {c}
                </span>
              ))}
            </div>
          </div>
        </Spotlight>
      </section>

      {/* What interviews actually ask */}
      <section className="mx-auto max-w-6xl px-5 py-14 md:py-20">
        <ScrollReveal>
          <Kicker>From the engine</Kicker>
          <h2 className="display mt-3 text-2xl text-ink md:text-4xl">
            What interviews actually ask
          </h2>
        </ScrollReveal>
        <div className="mt-7 grid gap-4 md:grid-cols-2">
          {page.asked.map((q, i) => (
            <motion.div
              key={q}
              className="rounded-[14px] border border-line bg-white p-5 shadow-soft"
              initial={{ opacity: 0, y: 14 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true, margin: "-50px" }}
              transition={{ duration: durations.slow, ease, delay: i * stagger }}
            >
              <span className="mono text-[10px] font-semibold uppercase tracking-[0.18em] text-trust">
                {String(i + 1).padStart(2, "0")}
              </span>
              <p className="mt-1.5 font-medium text-ink">{q}</p>
            </motion.div>
          ))}
        </div>
      </section>

      {/* Roles + pay */}
      <section className="mx-auto max-w-6xl px-5 pb-14 md:pb-20">
        <ScrollReveal>
          <Kicker>Where it pays</Kicker>
          <h2 className="display mt-3 text-2xl text-ink md:text-4xl">Roles built on {page.name}</h2>
        </ScrollReveal>
        <div className="mt-7 grid gap-5 md:grid-cols-2">
          {page.roles.map((r, i) => (
            <ScrollReveal key={r.name} delay={i * 0.06} className="h-full">
              <TiltCard className="h-full">
                <Link
                  href={r.salarySlug ? `/salaries/${r.salarySlug}` : "/salaries"}
                  className="group flex h-full items-center justify-between rounded-[14px] border border-line bg-white p-6 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:border-trust/40"
                >
                  <span className="font-semibold text-ink">{r.name}</span>
                  <span className="flex items-center gap-1.5 text-sm font-semibold text-trust">
                    Salary breakdown
                    <span className="transition-transform duration-300 group-hover:translate-x-1">→</span>
                  </span>
                </Link>
              </TiltCard>
            </ScrollReveal>
          ))}
        </div>
        {page.track && (
          <ScrollReveal delay={0.1}>
            <p className="mt-6 text-ink2/70">
              {page.name} is taught hands-on in the{" "}
              <Link href={page.track.href} className="font-semibold text-trust hover:underline">
                {page.track.name} track
              </Link>{" "}
              — rebuilt monthly from what interviews ask.
            </p>
          </ScrollReveal>
        )}
      </section>

      {/* Related skills */}
      <section className="mx-auto max-w-6xl px-5 pb-14 md:pb-20">
        <ScrollReveal>
          <span className="mono text-[10px] font-semibold uppercase tracking-[0.18em] text-muted">
            Usually asked together
          </span>
          <div className="mt-3 flex flex-wrap gap-2">
            {page.related.map((slug) => {
              const rel = getSkillPage(slug);
              return rel ? (
                <Link
                  key={slug}
                  href={`/skills/${slug}`}
                  className="mono rounded-full border border-line bg-white px-4 py-2 text-sm text-ink shadow-soft transition-colors hover:border-trust"
                >
                  {rel.name} {rel.direction === "rising" ? "↑" : "→"}
                </Link>
              ) : null;
            })}
          </div>
        </ScrollReveal>
      </section>

      {/* Close */}
      <section className="mx-auto max-w-6xl px-5 pb-16 md:pb-24">
        <ScrollReveal>
          <Spotlight
            className="grain rounded-[22px] bg-ink px-8 py-12 text-center text-white shadow-soft"
            backdrop={
              <div aria-hidden className="absolute inset-0 bg-cover bg-center opacity-50" style={{ backgroundImage: "url(/art/engine-aurora.svg)" }} />
            }
          >
            <p className="kicker text-sky/70">Free, before any fee</p>
            <h2 className="display mx-auto mt-3 max-w-xl text-2xl md:text-3xl">
              Learn {page.name} the way interviews test it
            </h2>
            <div className="mt-6">
              <BookCta className="px-7 py-3" />
            </div>
          </Spotlight>
        </ScrollReveal>
      </section>
    </>
  );
}

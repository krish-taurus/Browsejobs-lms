"use client";

import { useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease, stagger } from "@/lib/motion";
import { apiJson } from "@/lib/api";
import { Kicker } from "@/components/brand/Kicker";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { MaskReveal } from "@/components/motion/MaskReveal";
import { Spotlight } from "@/components/motion/Spotlight";
import { BookCta } from "@/components/landing/BookCta";

/**
 * The Daily Market Brief funnel page: the emailed headline lands here.
 * The brief renders blurred behind a register-to-read card (one submit to
 * /api/v1/leads unlocks it, remembered locally) — and the unlocked view ends
 * in the masterclass recommendation, closing the loop the daily message
 * opens. Every item carries its public source.
 */

type Item = {
  headline: string | null;
  company: string | null;
  sector: string;
  round: string;
  hub: string;
  hiring_lag_months: number;
  roles: string[];
  skills: string[];
  source_name: string;
  source_url: string | null;
  published_on: string;
};

type Brief = { date: string; headline: string | null; items: Record<"hiring" | "layoff" | "funding", Item[]>; total: number };

const GROUPS = [
  ["hiring", "Hiring announced", "text-verify"],
  ["layoff", "Cuts reported", "text-warn"],
  ["funding", "Funding → hiring signal", "text-trust"],
] as const;

const UNLOCK_KEY = "bj-brief-unlocked";

export function BriefView() {
  const reduce = useReducedMotion();
  const [brief, setBrief] = useState<Brief | null>(null);
  const [unlocked, setUnlocked] = useState(false);
  const [form, setForm] = useState({ name: "", phone: "" });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setUnlocked(window.localStorage.getItem(UNLOCK_KEY) === "1");
    apiJson<{ data: Brief }>("/api/v1/brief").then((r) => setBrief(r.data)).catch(() => {});
  }, []);

  const register = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await apiJson("/api/v1/leads", {
        method: "POST",
        body: JSON.stringify({
          lead_type: "counselling",
          name: form.name,
          phone: form.phone,
          email: null,
          course_slug: null,
          page: "/brief",
          consent: true,
        }),
      });
      window.localStorage.setItem(UNLOCK_KEY, "1");
      setUnlocked(true);
    } catch {
      setError("Could not register — check the phone number and try again.");
    } finally {
      setBusy(false);
    }
  };

  const items = brief?.items ?? { hiring: [], layoff: [], funding: [] };

  return (
    <>
      {/* Headline hero */}
      <section className="relative overflow-hidden">
        <Spotlight
          className="grain bg-ink text-white"
          backdrop={
            <div aria-hidden className="absolute inset-0 bg-cover bg-center opacity-55" style={{ backgroundImage: "url(/art/engine-aurora.svg)" }} />
          }
        >
          <div className="mx-auto max-w-4xl px-5 pb-14 pt-14 md:pb-20 md:pt-20">
            <Kicker tone="sky">Daily market brief{brief ? ` · ${brief.date}` : ""}</Kicker>
            <h1 className="display mt-4 text-[clamp(1.9rem,5vw,3.5rem)] leading-[1.1]">
              <MaskReveal index={0}>{brief?.headline ?? "Today's hiring signal"}</MaskReveal>
            </h1>
            <p className="mt-4 max-w-xl text-sky/70">
              Who announced hiring, who cut, and where the capital is flowing —
              compiled from public news, every item with its source.
            </p>
          </div>
        </Spotlight>
      </section>

      <section className="relative mx-auto min-h-[480px] max-w-4xl px-5 py-12 md:py-16">
        {/* The brief — blurred until registered. */}
        <div className={unlocked ? "" : "pointer-events-none select-none blur-md"} aria-hidden={!unlocked}>
          {GROUPS.map(([key, label, tone]) =>
            items[key].length === 0 ? null : (
              <ScrollReveal key={key}>
                <div className="mb-10">
                  <span className={`mono text-[10px] font-semibold uppercase tracking-[0.18em] ${tone}`}>{label}</span>
                  <ul className="mt-4 space-y-4">
                    {items[key].map((n, i) => (
                      <motion.li
                        key={`${n.sector}-${i}`}
                        className="rounded-[14px] border border-line bg-white p-5 shadow-soft"
                        initial={reduce ? undefined : { opacity: 0, y: 14 }}
                        whileInView={reduce ? undefined : { opacity: 1, y: 0 }}
                        viewport={{ once: true, margin: "-40px" }}
                        transition={{ duration: durations.slow, ease, delay: i * stagger }}
                      >
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                          <span className="font-semibold text-ink">
                            {n.headline ?? `${n.company ?? n.sector} · ${n.round}`}
                          </span>
                          <span className="mono text-xs text-muted">{n.hub} · {n.published_on}</span>
                        </div>
                        {n.roles.length > 0 && (
                          <p className="mono mt-2 text-xs text-muted">
                            expect: {n.roles.join(" · ")}{n.skills.length ? ` — ${n.skills.join(", ")}` : ""}
                          </p>
                        )}
                        <p className="mono mt-2 text-xs text-muted">
                          {n.source_url ? (
                            <a href={n.source_url} target="_blank" rel="noopener noreferrer" className="text-trust hover:underline">
                              {n.source_name} ↗
                            </a>
                          ) : (
                            n.source_name
                          )}
                        </p>
                      </motion.li>
                    ))}
                  </ul>
                </div>
              </ScrollReveal>
            ),
          )}

          {brief !== null && brief.total === 0 && (
            <p className="mono py-10 text-center text-sm text-muted">
              Today&apos;s brief is being compiled — check back shortly.
            </p>
          )}
        </div>

        {/* Register-to-read gate */}
        {!unlocked && (
          <div className="absolute inset-x-5 top-8 mx-auto max-w-md">
            <motion.div
              className="rounded-[22px] border border-line bg-white p-7 shadow-soft"
              initial={reduce ? undefined : { opacity: 0, y: 20 }}
              animate={reduce ? undefined : { opacity: 1, y: 0 }}
              transition={{ duration: durations.slow, ease }}
            >
              <Kicker>Free · 10 seconds</Kicker>
              <h2 className="display mt-2 text-xl text-ink">Register to read today&apos;s brief</h2>
              <p className="mt-2 text-sm text-ink2/70">
                One-time — the daily brief then stays open for you on this device.
              </p>
              <form className="mt-4 space-y-3" onSubmit={register}>
                <input
                  className="w-full rounded-[10px] border border-line px-3 py-2.5 text-sm outline-none focus:border-trust"
                  placeholder="Your name"
                  value={form.name}
                  onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                  required
                />
                <input
                  className="w-full rounded-[10px] border border-line px-3 py-2.5 text-sm outline-none focus:border-trust"
                  placeholder="WhatsApp number"
                  value={form.phone}
                  onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
                  required
                />
                {error && <p className="text-xs text-warn">{error}</p>}
                <button
                  type="submit"
                  disabled={busy}
                  className="w-full rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
                >
                  {busy ? "Opening…" : "Read the brief"}
                </button>
                <p className="mono text-[10px] leading-relaxed text-muted">
                  By continuing you agree to receive the daily brief on WhatsApp. Unsubscribe anytime.
                </p>
              </form>
            </motion.div>
          </div>
        )}
      </section>

      {/* Masterclass recommendation — the conversion close. */}
      {unlocked && (
        <section className="mx-auto max-w-4xl px-5 pb-16 md:pb-24">
          <ScrollReveal>
            <Spotlight
              className="grain rounded-[22px] bg-ink px-8 py-12 text-center text-white shadow-soft"
              backdrop={
                <div aria-hidden className="absolute inset-0 bg-cover bg-center opacity-50" style={{ backgroundImage: "url(/art/engine-aurora.svg)" }} />
              }
            >
              <p className="kicker text-sky/70">While it&apos;s fresh</p>
              <h2 className="display mx-auto mt-3 max-w-xl text-2xl md:text-3xl">
                See how we turn this signal into a syllabus — live
              </h2>
              <p className="mx-auto mt-3 max-w-md text-sky/70">
                The free masterclass shows the engine behind this brief, and how
                students train straight into the demand it finds.
              </p>
              <div className="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <BookCta className="px-7 py-3" />
                <a
                  href="/masterclass"
                  className="rounded-full border border-white/20 px-7 py-3 text-sm font-semibold text-white transition-colors hover:border-white/60"
                >
                  Watch the recording
                </a>
              </div>
            </Spotlight>
          </ScrollReveal>
        </section>
      )}
    </>
  );
}

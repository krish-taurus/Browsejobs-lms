"use client";

import { useState } from "react";
import Link from "next/link";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease, stagger } from "@/lib/motion";
import { ApiError, apiJson } from "@/lib/api";
import { Kicker } from "@/components/brand/Kicker";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { MonoCounter } from "@/components/motion/MonoCounter";
import { skillHref } from "@/content/skills";

/**
 * Free Career Direction Report — the register-to-analyse lead magnet. The
 * API refuses to run without name+phone+consent (server-enforced gate), and
 * the analysis is deterministic and unbiased: every path is scored the same
 * way, including ones BrowseJobs does not teach (labelled honestly). The
 * resume text is scored and discarded server-side.
 */

type Report = {
  ats: { score: number; checks: { label: string; pass: boolean; hint: string }[] };
  skills: { skill: string; trend: "rising" | "steady" }[];
  paths: { path: string; fit_pct: number; have: string[]; missing: string[]; taught_here: boolean }[];
  focus: { skill: string; trend: "rising" | "steady"; why: string }[];
  summary: { best_path: string; best_fit_pct: number; taught_here: boolean; rising_held: number; total_detected: number };
};

function ScoreRing({ score }: { score: number }) {
  const reduce = useReducedMotion();
  const R = 52, C = 2 * Math.PI * R;
  return (
    <svg viewBox="0 0 120 120" className="h-32 w-32">
      <circle cx="60" cy="60" r={R} fill="none" stroke="var(--bj-line)" strokeWidth="10" />
      <motion.circle
        cx="60" cy="60" r={R} fill="none" stroke="var(--bj-trust)" strokeWidth="10"
        strokeLinecap="round" strokeDasharray={C} transform="rotate(-90 60 60)"
        initial={reduce ? undefined : { strokeDashoffset: C }}
        whileInView={reduce ? undefined : { strokeDashoffset: C * (1 - score / 100) }}
        viewport={{ once: true }}
        style={reduce ? { strokeDashoffset: C * (1 - score / 100) } : undefined}
        transition={{ duration: durations.slower, ease }}
      />
      <text x="60" y="66" textAnchor="middle" className="mono" fontSize="26" fontWeight="600" fill="var(--bj-ink)">
        {score}
      </text>
    </svg>
  );
}

const inputCls = "w-full rounded-[10px] border border-line px-3 py-2.5 text-sm outline-none focus:border-trust";

export function CareerReport() {
  const [form, setForm] = useState({ name: "", phone: "", text: "" });
  const [report, setReport] = useState<Report | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const run = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true); setError(null);
    try {
      const res = await apiJson<{ data: Report }>("/api/v1/career-report", {
        method: "POST",
        body: JSON.stringify({ ...form, consent: true }),
      });
      setReport(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not build the report — try again.");
    } finally { setBusy(false); }
  };

  return (
    <section id="career-report" className="mx-auto max-w-6xl px-5 py-16 md:py-24">
      <ScrollReveal>
        <Kicker tone="verify">Free career report · unbiased</Kicker>
        <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-5xl">
          Where does the market say you should go next?
        </h2>
        <p className="mt-4 max-w-2xl text-ink2/70">
          Paste your resume, register once, and get a genuine direction report:
          what&apos;s working, what&apos;s missing, and which path fits your skills — even
          when it&apos;s one we don&apos;t teach. Your resume is analysed and discarded, never stored.
        </p>
      </ScrollReveal>

      {!report ? (
        <ScrollReveal delay={0.08}>
          <form onSubmit={run} className="mt-10 grid gap-5 rounded-[22px] border border-line bg-white p-7 shadow-soft lg:grid-cols-[1.2fr_0.8fr]">
            <textarea
              rows={9}
              className="resize-none rounded-[10px] border border-line bg-paper/60 p-4 text-sm leading-relaxed outline-none focus:border-trust"
              placeholder="Paste your resume text here…"
              value={form.text}
              onChange={(e) => setForm((f) => ({ ...f, text: e.target.value }))}
              required
            />
            <div className="flex flex-col justify-center gap-3">
              <p className="mono text-[10px] uppercase tracking-[0.18em] text-trust">Register to unlock your report</p>
              <input className={inputCls} placeholder="Your name" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} required />
              <input className={inputCls} placeholder="WhatsApp number" value={form.phone} onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} required />
              {error && <p className="text-xs text-warn">{error}</p>}
              <button type="submit" disabled={busy || form.text.trim().length < 80}
                className="rounded-full bg-trust px-6 py-3 font-semibold text-white transition-colors hover:bg-deep disabled:opacity-40">
                {busy ? "Analysing…" : "Get my free report"}
              </button>
              <p className="mono text-[10px] leading-relaxed text-muted">
                By continuing you agree to be contacted about your report. The
                analysis runs only after registration.
              </p>
            </div>
          </form>
        </ScrollReveal>
      ) : (
        <div className="mt-10 space-y-5">
          {/* Verdict + ATS ring */}
          <ScrollReveal>
            <div className="grid gap-5 md:grid-cols-[1fr_auto] md:items-center rounded-[22px] border border-line bg-white p-7 shadow-soft">
              <div>
                <p className="mono text-[10px] uppercase tracking-[0.18em] text-muted">Best-fit direction</p>
                <p className="display mt-1 text-3xl text-ink">
                  {report.summary.best_path}
                  <span className="mono ml-3 text-xl text-trust"><MonoCounter value={report.summary.best_fit_pct} plain className="inline" />% fit</span>
                </p>
                <p className="mt-2 max-w-lg text-sm text-ink2/70">
                  {report.summary.taught_here
                    ? "This is a track we teach — but the fit score above is computed the same way for every path, including ones we don't."
                    : "We don't currently teach this path — it's still your honest best fit, and the focus list below applies wherever you learn it."}
                </p>
              </div>
              <div className="justify-self-center text-center">
                <ScoreRing score={report.ats.score} />
                <p className="mono text-[10px] uppercase tracking-[0.16em] text-muted">ATS score</p>
              </div>
            </div>
          </ScrollReveal>

          {/* Path fit bars — every path, taught or not */}
          <ScrollReveal>
            <div className="rounded-[22px] border border-line bg-white p-7 shadow-soft">
              <p className="mono text-[10px] font-semibold uppercase tracking-[0.18em] text-trust">Path fit · scored identically for every path</p>
              <ul className="mt-5 space-y-4">
                {report.paths.map((p) => (
                  <li key={p.path}>
                    <div className="flex items-baseline justify-between gap-3">
                      <span className="font-semibold text-ink">
                        {p.path}
                        {!p.taught_here && <span className="mono ml-2 text-[10px] uppercase tracking-wide text-muted">not our course</span>}
                      </span>
                      <span className="mono text-xs text-ink">{p.fit_pct}%</span>
                    </div>
                    <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-sky">
                      <motion.div className="h-full rounded-full bg-trust" style={{ transformOrigin: "left" }}
                        initial={{ scaleX: 0 }} whileInView={{ scaleX: p.fit_pct / 100 }}
                        viewport={{ once: true, margin: "-40px" }} transition={{ duration: durations.slower, ease }} />
                    </div>
                    {p.missing.length > 0 && (
                      <p className="mono mt-1 text-[11px] text-muted">missing: {p.missing.join(" · ")}</p>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          </ScrollReveal>

          <div className="grid gap-5 lg:grid-cols-2">
            {/* What's working */}
            <ScrollReveal className="h-full">
              <div className="h-full rounded-[22px] border border-line bg-white p-7 shadow-soft">
                <p className="mono text-[10px] font-semibold uppercase tracking-[0.18em] text-verify">What&apos;s working ({report.summary.total_detected} skills found)</p>
                <div className="mt-4 flex flex-wrap gap-2">
                  {report.skills.map((s, i) => {
                    const href = skillHref(s.skill);
                    const cls = `mono inline-block rounded-full border px-3.5 py-1.5 text-sm shadow-soft ${s.trend === "rising" ? "border-trust/40 text-trust" : "border-line text-ink"}`;
                    return (
                      <motion.span key={s.skill} initial={{ opacity: 0, y: 10 }} whileInView={{ opacity: 1, y: 0 }}
                        viewport={{ once: true, margin: "-40px" }} transition={{ duration: durations.slow, ease, delay: i * stagger }}>
                        {href ? <Link href={href} className={cls}>{s.skill} {s.trend === "rising" ? "↑" : ""}</Link>
                          : <span className={cls}>{s.skill} {s.trend === "rising" ? "↑" : ""}</span>}
                      </motion.span>
                    );
                  })}
                </div>
                <p className="mono mt-4 text-[11px] text-muted">↑ = rising demand in the market signals we track</p>
              </div>
            </ScrollReveal>

            {/* Fix list from ATS */}
            <ScrollReveal delay={0.06} className="h-full">
              <div className="h-full rounded-[22px] border border-line bg-white p-7 shadow-soft">
                <p className="mono text-[10px] font-semibold uppercase tracking-[0.18em] text-warn">What to fix on the resume</p>
                <ul className="mt-4 space-y-3">
                  {report.ats.checks.map((c) => (
                    <li key={c.label} className="border-t border-line pt-3 first:border-t-0 first:pt-0">
                      <div className="flex items-center justify-between gap-3">
                        <span className="text-sm font-semibold text-ink">{c.label}</span>
                        <span className={`mono text-xs ${c.pass ? "text-verify" : "text-warn"}`}>{c.pass ? "pass" : "fix"}</span>
                      </div>
                      {!c.pass && <p className="mt-1 text-xs text-ink2/70">{c.hint}</p>}
                    </li>
                  ))}
                </ul>
              </div>
            </ScrollReveal>
          </div>

          {/* Focus next */}
          <ScrollReveal>
            <div className="rounded-[22px] border border-line bg-white p-7 shadow-soft">
              <p className="mono text-[10px] font-semibold uppercase tracking-[0.18em] text-trust">Focus next · from market demand</p>
              <div className="mt-5 grid gap-4 md:grid-cols-3">
                {report.focus.map((f, i) => (
                  <div key={f.skill} className="rounded-[14px] border border-line p-4">
                    <span className="mono text-[10px] font-semibold text-trust">{String(i + 1).padStart(2, "0")}</span>
                    <p className="mt-1 font-semibold text-ink">{f.skill} {f.trend === "rising" ? <span className="mono text-xs text-trust">rising ↑</span> : null}</p>
                    <p className="mt-1 text-xs leading-relaxed text-ink2/70">{f.why}</p>
                  </div>
                ))}
              </div>
              <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
                <Disclaimer />
                <button type="button" onClick={() => setReport(null)} className="mono shrink-0 text-xs text-muted hover:text-ink">
                  ↺ analyse another resume
                </button>
              </div>
            </div>
          </ScrollReveal>
        </div>
      )}
    </section>
  );
}

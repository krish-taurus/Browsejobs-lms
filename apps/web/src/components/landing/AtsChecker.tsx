"use client";

import { useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { Kicker } from "@/components/brand/Kicker";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { MonoCounter } from "@/components/motion/MonoCounter";
import { openLeadModal } from "@/components/landing/leadModalBus";

/**
 * Free ATS quick-check — the homepage lead magnet: paste resume text, get a
 * deterministic score + concrete fixes instantly (nothing stored, no AI).
 * The follow-up CTA routes into the standard lead modal. Green is reserved
 * for pass states (verified/free semantics); failures use warn red.
 */

type Check = { label: string; pass: boolean; hint: string };
type Result = { score: number; checks: Check[] };

export function AtsChecker() {
  const [text, setText] = useState("");
  const [result, setResult] = useState<Result | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const run = async () => {
    setBusy(true);
    setError(null);
    try {
      const res = await apiJson<{ data: Result }>("/api/v1/ats-check", {
        method: "POST",
        body: JSON.stringify({ text }),
      });
      setResult(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not check right now — try again.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <section id="ats-check" className="mx-auto max-w-6xl px-5 py-16 md:py-24">
      <ScrollReveal>
        <Kicker tone="verify">Free tool · nothing stored</Kicker>
        <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-5xl">
          Will your resume survive the ATS?
        </h2>
        <p className="mt-4 max-w-2xl text-ink2/70">
          Paste your resume text below. Five checks, scored instantly on this
          page — the same parse rules recruiters&apos; software applies before a
          human ever reads you.
        </p>
      </ScrollReveal>

      <ScrollReveal delay={0.08}>
        <div className="mt-10 grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
          <div className="flex flex-col rounded-[22px] border border-line bg-white p-6 shadow-soft">
            <textarea
              value={text}
              onChange={(e) => setText(e.target.value)}
              rows={10}
              placeholder="Paste your resume text here (copy-paste from your PDF or doc)…"
              className="flex-1 resize-none rounded-[10px] border border-line bg-paper/60 p-4 text-sm leading-relaxed text-ink outline-none focus:border-trust"
            />
            <div className="mt-4 flex items-center justify-between gap-3">
              <p className="mono text-[10px] text-muted">
                {error ?? "Scored in your request only — we don't keep the text."}
              </p>
              <button
                type="button"
                onClick={run}
                disabled={busy || text.trim().length < 80}
                className="rounded-full bg-trust px-6 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-40"
              >
                {busy ? "Checking…" : "Check my resume"}
              </button>
            </div>
          </div>

          <div className="rounded-[22px] border border-line bg-white p-6 shadow-soft">
            {result ? (
              <>
                <div className="flex items-baseline justify-between">
                  <span className="mono text-[10px] uppercase tracking-[0.18em] text-muted">ATS score</span>
                  <span className={`mono text-5xl font-semibold ${result.score >= 80 ? "text-verify" : result.score >= 50 ? "text-ink" : "text-warn"}`}>
                    <MonoCounter value={result.score} plain className="inline" />
                    <span className="text-xl">/100</span>
                  </span>
                </div>
                <ul className="mt-5 space-y-3">
                  {result.checks.map((c) => (
                    <li key={c.label} className="border-t border-line pt-3 first:border-t-0 first:pt-0">
                      <div className="flex items-center justify-between gap-3">
                        <span className="text-sm font-semibold text-ink">{c.label}</span>
                        <span className={`mono text-xs ${c.pass ? "text-verify" : "text-warn"}`}>
                          {c.pass ? "pass" : "fix"}
                        </span>
                      </div>
                      {!c.pass && <p className="mt-1 text-xs leading-relaxed text-ink2/70">{c.hint}</p>}
                    </li>
                  ))}
                </ul>
                <button
                  type="button"
                  onClick={() => openLeadModal({ variant: "masterclass" })}
                  className="mt-5 w-full rounded-full border border-line px-5 py-2.5 text-sm font-semibold text-ink transition-colors hover:border-trust"
                >
                  Get the full AI-reviewed report — free with counselling
                </button>
              </>
            ) : (
              <div className="flex h-full min-h-[260px] flex-col items-center justify-center text-center">
                <p className="mono text-[10px] uppercase tracking-[0.18em] text-muted">Your score appears here</p>
                <p className="mt-2 max-w-xs text-sm text-ink2/60">
                  Five checks: sections, contact details, action verbs,
                  quantified results, and length.
                </p>
              </div>
            )}
          </div>
        </div>
      </ScrollReveal>
    </section>
  );
}

"use client";

import { useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { apiJson } from "@/lib/api";
import { Kicker } from "@/components/brand/Kicker";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { MaskReveal } from "@/components/motion/MaskReveal";
import { Spotlight } from "@/components/motion/Spotlight";
import { BookCta } from "@/components/landing/BookCta";
import { MASTERCLASS_RECORDING_URL } from "@/content/landing";

/**
 * /masterclass — watch-anytime page. When a recording URL is configured it
 * embeds behind the same one-time register gate the brief uses (a watch is a
 * lead); until then the page sells the live session honestly. The live CTA
 * is always the loudest element.
 */

const UNLOCK_KEY = "bj-brief-unlocked"; // one registration unlocks brief + recording

export function MasterclassView() {
  const reduce = useReducedMotion();
  const [unlocked, setUnlocked] = useState(false);
  const [form, setForm] = useState({ name: "", phone: "" });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setUnlocked(window.localStorage.getItem(UNLOCK_KEY) === "1");
  }, []);

  const register = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await apiJson("/api/v1/leads", {
        method: "POST",
        body: JSON.stringify({
          lead_type: "masterclass",
          name: form.name,
          phone: form.phone,
          email: null,
          course_slug: null,
          page: "/masterclass",
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

  return (
    <>
      <section className="relative overflow-hidden">
        <Spotlight
          className="grain bg-ink text-white"
          backdrop={
            <div aria-hidden className="absolute inset-0 bg-cover bg-center opacity-55" style={{ backgroundImage: "url(/art/engine-aurora.svg)" }} />
          }
        >
          <div className="mx-auto max-w-4xl px-5 pb-14 pt-14 text-center md:pb-20 md:pt-20">
            <Kicker tone="verify">Free masterclass</Kicker>
            <h1 className="display mt-4 text-[clamp(2rem,5.5vw,3.75rem)] leading-[1.08]">
              <MaskReveal index={0}>See the method live</MaskReveal>
              <MaskReveal index={1} className="text-trust">before you spend a rupee</MaskReveal>
            </h1>
            <p className="mx-auto mt-4 max-w-xl text-sky/75">
              How the syllabus is reverse-engineered from real interviews — the
              session every student attends before deciding anything.
            </p>
            <div className="mt-7">
              <BookCta className="px-8 py-3.5" />
            </div>
          </div>
        </Spotlight>
      </section>

      <section className="mx-auto max-w-4xl px-5 py-12 md:py-16">
        {MASTERCLASS_RECORDING_URL === null ? (
          <ScrollReveal>
            <div className="rounded-[22px] border border-line bg-white p-8 text-center shadow-soft">
              <span className="mono text-[10px] font-semibold uppercase tracking-[0.18em] text-muted">
                Recording
              </span>
              <h2 className="display mt-2 text-xl text-ink">The latest recording lands here after each live session</h2>
              <p className="mx-auto mt-2 max-w-md text-sm text-ink2/70">
                Book the live session above — attendees get the recording first,
                inside their dashboard.
              </p>
            </div>
          </ScrollReveal>
        ) : unlocked ? (
          <ScrollReveal>
            <div className="overflow-hidden rounded-[22px] border border-line shadow-soft">
              <div className="aspect-video w-full">
                <iframe
                  src={MASTERCLASS_RECORDING_URL}
                  title="BrowseJobs masterclass recording"
                  className="h-full w-full"
                  allow="accelerometer; autoplay; encrypted-media; picture-in-picture"
                  allowFullScreen
                />
              </div>
            </div>
            <p className="mono mt-3 text-center text-[11px] text-muted">
              Prefer it live? The next session goes deeper and takes questions — book above.
            </p>
          </ScrollReveal>
        ) : (
          <motion.div
            className="mx-auto max-w-md rounded-[22px] border border-line bg-white p-7 shadow-soft"
            initial={reduce ? undefined : { opacity: 0, y: 20 }}
            animate={reduce ? undefined : { opacity: 1, y: 0 }}
            transition={{ duration: durations.slow, ease }}
          >
            <Kicker>Free · 10 seconds</Kicker>
            <h2 className="display mt-2 text-xl text-ink">Register to watch the recording</h2>
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
                {busy ? "Opening…" : "Watch the recording"}
              </button>
            </form>
          </motion.div>
        )}
      </section>
    </>
  );
}

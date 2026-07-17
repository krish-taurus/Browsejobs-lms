"use client";

import { useCallback, useEffect, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import Link from "next/link";
import { ApiError, apiJson } from "@/lib/api";
import { durations, ease } from "@/lib/motion";

/** First-touch UTM capture, persisted for attribution (spec §6.1). */
function captureUtm(): Record<string, string> {
  try {
    const stored = sessionStorage.getItem("bj_utm");
    if (stored) return JSON.parse(stored) as Record<string, string>;
    const params = new URLSearchParams(window.location.search);
    const utm: Record<string, string> = {};
    for (const key of ["utm_source", "utm_medium", "utm_campaign"]) {
      const v = params.get(key);
      if (v) utm[key] = v;
    }
    if (Object.keys(utm).length) sessionStorage.setItem("bj_utm", JSON.stringify(utm));
    return utm;
  } catch {
    return {};
  }
}

/**
 * Lead-gated syllabus download (PRD §6.1/§6.2). Collects name + phone (DPDP consent),
 * captures a `syllabus` lead, and opens the signed PDF/HTML on success. If no approved
 * syllabus exists yet the endpoint 404s and we nudge the free counselling step.
 */
export function SyllabusDownload({ courseSlug }: { courseSlug: string }) {
  const reduce = useReducedMotion();
  const [open, setOpen] = useState(false);
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [consent, setConsent] = useState(false);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notReady, setNotReady] = useState(false);

  const close = useCallback(() => setOpen(false), []);

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") close();
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [close]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const res = await apiJson<{ data: { download: string | null } }>(
        `/api/v1/courses/${courseSlug}/syllabus/download`,
        {
          method: "POST",
          body: JSON.stringify({ name, phone, ...captureUtm(), consent }),
        },
      );
      setDone(true);
      if (res.data.download) window.open(res.data.download, "_blank", "noopener");
    } catch (err) {
      if (err instanceof ApiError && err.status === 404) {
        setNotReady(true);
      } else {
        setError(
          err instanceof ApiError
            ? (err.firstError ?? err.message)
            : "Something went wrong. Please try again or call us.",
        );
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <button
        onClick={() => {
          setOpen(true);
          setDone(false);
          setNotReady(false);
          setError(null);
        }}
        className="rounded-full border border-white/40 px-8 py-3.5 text-sm font-semibold text-white transition-colors hover:border-white"
      >
        Download syllabus
      </button>

      <AnimatePresence>
        {open && (
          <motion.div
            className="fixed inset-0 z-[100] grid place-items-center bg-ink/50 p-4 backdrop-blur-sm"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: durations.fast }}
            onClick={close}
            role="dialog"
            aria-modal="true"
            aria-label="Download syllabus"
          >
            <motion.div
              className="w-full max-w-md overflow-hidden rounded-[22px] bg-white shadow-soft"
              initial={reduce ? false : { opacity: 0, y: 18, scale: 0.98 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={reduce ? undefined : { opacity: 0, y: 12, scale: 0.98 }}
              transition={{ duration: durations.base, ease }}
              onClick={(e) => e.stopPropagation()}
            >
              {notReady ? (
                <div className="p-8 text-center">
                  <h2 className="display text-xl text-ink">Syllabus coming soon</h2>
                  <p className="mt-2 text-sm text-muted">
                    This edition is being finalised. Book free counselling and we&apos;ll walk you
                    through the current, monthly-rebuilt syllabus live.
                  </p>
                  <button
                    onClick={close}
                    className="mt-6 rounded-full border border-line px-6 py-2.5 text-sm font-semibold text-ink hover:border-trust"
                  >
                    Done
                  </button>
                </div>
              ) : done ? (
                <div className="p-8 text-center">
                  <div className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-verify-bg">
                    <svg viewBox="0 0 24 24" className="h-7 w-7 text-verify" fill="none">
                      <path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                  </div>
                  <h2 className="display mt-4 text-xl text-ink">Your syllabus is downloading.</h2>
                  <p className="mt-2 text-sm text-muted">
                    A counsellor will follow up on WhatsApp with the free next steps.
                  </p>
                  <button
                    onClick={close}
                    className="mt-6 rounded-full border border-line px-6 py-2.5 text-sm font-semibold text-ink hover:border-trust"
                  >
                    Done
                  </button>
                </div>
              ) : (
                <form onSubmit={submit} className="p-7">
                  <p className="kicker text-verify">Free · syllabus PDF</p>
                  <h2 className="display mt-2 text-xl text-ink">Get the full syllabus</h2>
                  <p className="mt-1.5 text-sm text-muted">
                    Built from real interviews and rebuilt monthly. Leave your details and it&apos;s
                    yours.
                  </p>

                  {error && (
                    <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>
                  )}

                  <div className="mt-5 space-y-3">
                    <input
                      required
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      placeholder="Your name"
                      className="w-full rounded-[10px] border border-line bg-paper px-4 py-3 text-ink outline-none focus:border-trust focus:ring-2 focus:ring-trust/25"
                    />
                    <input
                      required
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      placeholder="WhatsApp number"
                      inputMode="tel"
                      className="w-full rounded-[10px] border border-line bg-paper px-4 py-3 text-ink outline-none focus:border-trust focus:ring-2 focus:ring-trust/25"
                    />
                  </div>

                  <label className="mt-4 flex items-start gap-2.5 text-xs text-muted">
                    <input
                      type="checkbox"
                      required
                      checked={consent}
                      onChange={(e) => setConsent(e.target.checked)}
                      className="mt-0.5 h-4 w-4 accent-[var(--bj-trust)]"
                    />
                    <span>
                      I agree to be contacted on WhatsApp/phone/email and accept the{" "}
                      <Link href="/privacy-policy" className="text-trust hover:underline">
                        privacy policy
                      </Link>
                      . Calls are recorded &amp; AI-monitored.
                    </span>
                  </label>

                  <button
                    disabled={busy || !consent}
                    className="mt-5 w-full rounded-full bg-trust py-3 font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
                  >
                    {busy ? "Preparing…" : "Download syllabus"}
                  </button>
                </form>
              )}
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </>
  );
}

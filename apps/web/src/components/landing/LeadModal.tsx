"use client";

import { useCallback, useEffect, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ApiError, apiJson } from "@/lib/api";
import { durations, ease } from "@/lib/motion";
import { thankYouPath } from "@/lib/thankYou";
import { courses } from "@/content/landing";
import {
  LEAD_MODAL_EVENT,
  type LeadModalDetail,
  type LeadVariant,
} from "@/components/landing/leadModalBus";

const COPY: Record<LeadVariant, { title: string; body: string; cta: string }> = {
  masterclass: {
    title: "Book your free masterclass",
    body: "A live session on how the syllabus is reverse-engineered from real interviews. Free — the first of three free steps.",
    cta: "Book my free seat",
  },
  counselling: {
    title: "Free counselling + written report",
    body: "Talk to a counsellor and leave with a written Career Analysis Report — whether you join or not.",
    cta: "Book my free call",
  },
  waitlist: {
    title: "Join the waitlist",
    body: "This program is opening soon. Leave your details and you'll be first to know.",
    cta: "Join the waitlist",
  },
};

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

export function LeadModal() {
  const reduce = useReducedMotion();
  const [detail, setDetail] = useState<LeadModalDetail | null>(null);
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [email, setEmail] = useState("");
  const [courseSlug, setCourseSlug] = useState("");
  const [consent, setConsent] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const router = useRouter();

  useEffect(() => {
    function onOpen(e: Event) {
      const d = (e as CustomEvent<LeadModalDetail>).detail;
      setDetail(d);
      setCourseSlug(d.courseSlug ?? "");
      setError(null);
    }
    window.addEventListener(LEAD_MODAL_EVENT, onOpen);
    captureUtm(); // capture on first paint so attribution survives navigation
    return () => window.removeEventListener(LEAD_MODAL_EVENT, onOpen);
  }, []);

  const close = useCallback(() => setDetail(null), []);

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") close();
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [close]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!detail) return;
    setBusy(true);
    setError(null);
    try {
      await apiJson("/api/v1/leads", {
        method: "POST",
        body: JSON.stringify({
          lead_type: detail.variant,
          name,
          phone,
          email: email || null,
          course_slug: courseSlug || null,
          ...captureUtm(),
          page: window.location.pathname,
          consent,
        }),
      });
      // Hand the person a real confirmation page rather than a modal that
      // vanishes: it names what they asked for and what happens next. `busy`
      // deliberately stays true so the button can't be pressed twice while the
      // navigation is in flight.
      router.push(thankYouPath(detail.variant, courseSlug || null));
    } catch (err) {
      setError(
        err instanceof ApiError
          ? (err.firstError ?? err.message)
          : "Something went wrong. Please try again or call us.",
      );
      setBusy(false);
    }
  }

  const copy = detail ? COPY[detail.variant] : COPY.masterclass;

  return (
    <AnimatePresence>
      {detail && (
        <motion.div
          className="fixed inset-0 z-[100] grid place-items-center bg-ink/50 p-4 backdrop-blur-sm"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: durations.fast }}
          onClick={close}
          role="dialog"
          aria-modal="true"
          aria-label={copy.title}
        >
          <motion.div
            className="w-full max-w-md overflow-hidden rounded-[22px] bg-white shadow-soft"
            initial={reduce ? false : { opacity: 0, y: 18, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={reduce ? undefined : { opacity: 0, y: 12, scale: 0.98 }}
            transition={{ duration: durations.base, ease }}
            onClick={(e) => e.stopPropagation()}
          >
            {/* No inline success state — a submitted form navigates to
                /thank-you, which carries the course recap and next steps. */}
            <form onSubmit={submit} className="p-7">
                <p className="kicker text-verify">Free · no card required</p>
                <h2 className="display mt-2 text-xl text-ink">{copy.title}</h2>
                <p className="mt-1.5 text-sm text-muted">{copy.body}</p>

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
                  <input
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="Email (optional)"
                    type="email"
                    className="w-full rounded-[10px] border border-line bg-paper px-4 py-3 text-ink outline-none focus:border-trust focus:ring-2 focus:ring-trust/25"
                  />
                  <select
                    value={courseSlug}
                    onChange={(e) => setCourseSlug(e.target.value)}
                    className="w-full rounded-[10px] border border-line bg-paper px-4 py-3 text-ink outline-none focus:border-trust"
                  >
                    <option value="">Which program interests you?</option>
                    {courses.map((c) => (
                      <option key={c.slug} value={c.slug}>
                        {c.name}
                        {c.live ? "" : " (waitlist)"}
                      </option>
                    ))}
                  </select>
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
                  {busy ? "Sending…" : copy.cta}
                </button>
              </form>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

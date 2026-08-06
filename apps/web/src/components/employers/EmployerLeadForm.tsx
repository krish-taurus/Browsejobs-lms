"use client";

import Link from "next/link";
import { useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { ApiError, apiJson } from "@/lib/api";
import { durations, ease } from "@/lib/motion";
import { captureUtm } from "@/lib/utm";

const FIELD =
  "w-full rounded-[10px] border border-white/12 bg-white/[0.04] px-4 py-3 text-white outline-none transition-shadow placeholder:text-white/30 focus:border-trust focus:ring-2 focus:ring-trust/30";

const OPENINGS = ["1–2", "3–5", "6–15", "16+"];
const TIMELINES = ["Hiring now", "This quarter", "Next quarter", "Exploring"];

/**
 * The employer enquiry form. It posts to the same public lead endpoint every
 * other form on the site uses, with `lead_type: "employer"` — so an employer
 * enquiry lands on the CRM pipeline with a stage, an owner, a score and a
 * speed-to-lead SLA, rather than in an inbox nobody owns.
 *
 * The hiring-specific answers (company, roles, openings, timeline) go in the
 * lead's `message`. That deliberately avoids adding employer-only columns to
 * the leads table for a form the sales team reads as prose anyway.
 */
export function EmployerLeadForm() {
  const reduce = useReducedMotion();
  const [company, setCompany] = useState("");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [roles, setRoles] = useState("");
  const [openings, setOpenings] = useState(OPENINGS[1]);
  const [timeline, setTimeline] = useState(TIMELINES[0]);
  const [consent, setConsent] = useState(false);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await apiJson("/api/v1/leads", {
        method: "POST",
        body: JSON.stringify({
          lead_type: "employer",
          name,
          phone,
          email,
          message: [
            `Company: ${company}`,
            `Roles: ${roles || "not specified"}`,
            `Openings: ${openings}`,
            `Timeline: ${timeline}`,
          ].join("\n"),
          ...captureUtm(),
          page: window.location.pathname,
          consent,
        }),
      });
      setDone(true);
    } catch (err) {
      setError(
        err instanceof ApiError
          ? (err.firstError ?? err.message)
          : "Something went wrong. Please try again, or email hello@browsejobs.ai.",
      );
    } finally {
      setBusy(false);
    }
  }

  if (done) {
    return (
      <motion.div
        initial={reduce ? false : { opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: durations.slow, ease }}
        className="grain relative overflow-hidden rounded-[22px] border border-white/10 bg-deck-card p-8 text-center"
      >
        <div className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-verify/15">
          <svg viewBox="0 0 24 24" className="h-7 w-7 text-verify" fill="none" aria-hidden>
            <path
              d="M5 12.5l4.5 4.5L19 7.5"
              stroke="currentColor"
              strokeWidth="2.2"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </div>
        <h3 className="display mt-4 text-xl text-white">We have it.</h3>
        <p className="mx-auto mt-2 max-w-sm text-sm text-white/55">
          Someone will come back to you on the details of the roles. If it is faster for you,
          reach us on <span className="mono text-white/80">hello@browsejobs.ai</span> or{" "}
          <span className="mono text-white/80">+91 86185 19825</span>.
        </p>
      </motion.div>
    );
  }

  return (
    <form
      onSubmit={submit}
      className="grain relative overflow-hidden rounded-[22px] border border-white/10 bg-deck-card p-6 md:p-8"
    >
      <p className="kicker text-verify">Free for 6 months · ₹0</p>
      <h3 className="display mt-2 text-xl text-white">Start a hiring conversation</h3>
      <p className="mt-1.5 text-sm text-white/55">
        We will tell you honestly whether the pool fits the role before anyone talks about a
        workspace.
      </p>

      {error && (
        <p className="mt-5 rounded-[10px] border border-warn/40 bg-warn/10 px-3 py-2 text-sm text-warn" role="alert">
          {error}
        </p>
      )}

      <div className="mt-6 grid gap-3 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <label className="text-sm font-medium text-white/70" htmlFor="employer-company">
            Company
          </label>
          <input
            id="employer-company"
            required
            value={company}
            onChange={(e) => setCompany(e.target.value)}
            placeholder="Meridian Logistics"
            className={`mt-1 ${FIELD}`}
          />
        </div>

        <div>
          <label className="text-sm font-medium text-white/70" htmlFor="employer-name">
            Your name
          </label>
          <input
            id="employer-name"
            required
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Divya Prasad"
            className={`mt-1 ${FIELD}`}
          />
        </div>

        <div>
          <label className="text-sm font-medium text-white/70" htmlFor="employer-email">
            Work email
          </label>
          <input
            id="employer-email"
            type="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="you@company.com"
            autoComplete="email"
            className={`mt-1 ${FIELD}`}
          />
        </div>

        <div>
          <label className="text-sm font-medium text-white/70" htmlFor="employer-phone">
            Phone
          </label>
          <input
            id="employer-phone"
            required
            inputMode="tel"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            placeholder="+91…"
            autoComplete="tel"
            className={`mt-1 ${FIELD}`}
          />
        </div>

        <div>
          <label className="text-sm font-medium text-white/70" htmlFor="employer-roles">
            Roles you are hiring for
          </label>
          <input
            id="employer-roles"
            value={roles}
            onChange={(e) => setRoles(e.target.value)}
            placeholder="Data engineer, analyst"
            className={`mt-1 ${FIELD}`}
          />
        </div>

        <div>
          <label className="text-sm font-medium text-white/70" htmlFor="employer-openings">
            Openings
          </label>
          <select
            id="employer-openings"
            value={openings}
            onChange={(e) => setOpenings(e.target.value)}
            className={`mt-1 ${FIELD}`}
          >
            {OPENINGS.map((o) => (
              <option key={o} value={o}>
                {o}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="text-sm font-medium text-white/70" htmlFor="employer-timeline">
            Timeline
          </label>
          <select
            id="employer-timeline"
            value={timeline}
            onChange={(e) => setTimeline(e.target.value)}
            className={`mt-1 ${FIELD}`}
          >
            {TIMELINES.map((t) => (
              <option key={t} value={t}>
                {t}
              </option>
            ))}
          </select>
        </div>
      </div>

      <label className="mt-5 flex items-start gap-2.5 text-xs text-white/45">
        <input
          type="checkbox"
          required
          checked={consent}
          onChange={(e) => setConsent(e.target.checked)}
          className="mt-0.5 h-4 w-4 accent-[var(--bj-trust)]"
        />
        <span>
          I agree to be contacted about hiring on phone, WhatsApp or email, and accept the{" "}
          <Link href="/privacy-policy" className="text-trust hover:underline">
            privacy policy
          </Link>
          . Calls are recorded &amp; AI-monitored.
        </span>
      </label>

      <button
        disabled={busy || !consent}
        className="mt-6 w-full rounded-full bg-trust py-3 font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
      >
        {busy ? "Sending…" : "Send the enquiry"}
      </button>
    </form>
  );
}

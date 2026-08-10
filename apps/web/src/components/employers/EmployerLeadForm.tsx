"use client";

/**
 * Employer enquiry capture. Posts `lead_type=employer` to the same public
 * /api/v1/leads endpoint every other site form uses, so the enquiry lands on
 * the CRM pipeline with a stage, an owner and the speed-to-lead SLA. Hiring
 * volume is folded into `message` — the CRM shows it on the lead timeline.
 *
 * DPDP: explicit consent is required before the button enables (spec §3.4).
 */

import { useState } from "react";
import Link from "next/link";
import { motion } from "framer-motion";
import { ApiError, apiJson } from "@/lib/api";
import { durations, ease } from "@/lib/motion";
import { HIRING_VOLUMES } from "@/content/employers";

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

const inputClass =
  "w-full rounded-input border border-white/15 bg-white/[0.06] px-4 py-3 text-[15px] text-white placeholder:text-white/35 outline-none transition-colors focus:border-trust focus:ring-2 focus:ring-trust/30";

export function EmployerLeadForm() {
  const [name, setName] = useState("");
  const [company, setCompany] = useState("");
  const [phone, setPhone] = useState("");
  const [email, setEmail] = useState("");
  const [volume, setVolume] = useState("");
  const [roles, setRoles] = useState("");
  const [consent, setConsent] = useState(false);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);

    const message = [volume && `Hiring volume: ${volume}`, roles && `Roles: ${roles}`]
      .filter(Boolean)
      .join("\n");

    try {
      await apiJson("/api/v1/leads", {
        method: "POST",
        body: JSON.stringify({
          lead_type: "employer",
          name,
          company,
          phone,
          email: email || null,
          message: message || null,
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
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: durations.slow, ease }}
        className="rounded-panel border border-verify/40 bg-verify/10 p-8 text-center"
      >
        <div className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-verify/20">
          <svg viewBox="0 0 24 24" className="h-7 w-7 text-verify" fill="none">
            <path
              d="M5 12.5l4.5 4.5L19 7.5"
              stroke="currentColor"
              strokeWidth="2.2"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </div>
        <h3 className="display mt-4 text-2xl text-white">Thanks — we have it.</h3>
        <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-white/60">
          Someone from the team will reach out to set up the onboarding call, walk through the pipeline on one of your
          live roles, and put the commercials in writing before anything starts.
        </p>
      </motion.div>
    );
  }

  return (
    <form onSubmit={submit} className="rounded-panel border border-white/12 bg-white/[0.04] p-6 md:p-8">
      {error && (
        <p role="alert" className="mb-5 rounded-input bg-warn/15 px-4 py-3 text-sm text-warn">
          {error}
        </p>
      )}

      <div className="grid gap-3 sm:grid-cols-2">
        <label className="block">
          <span className="sr-only">Your name</span>
          <input
            required
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Your name"
            autoComplete="name"
            className={inputClass}
          />
        </label>
        <label className="block">
          <span className="sr-only">Company</span>
          <input
            required
            value={company}
            onChange={(e) => setCompany(e.target.value)}
            placeholder="Company"
            autoComplete="organization"
            className={inputClass}
          />
        </label>
        <label className="block">
          <span className="sr-only">Work phone</span>
          <input
            required
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            placeholder="Work phone"
            inputMode="tel"
            autoComplete="tel"
            className={inputClass}
          />
        </label>
        <label className="block">
          <span className="sr-only">Work email</span>
          <input
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="Work email"
            type="email"
            autoComplete="email"
            className={inputClass}
          />
        </label>
        <label className="block">
          <span className="sr-only">Hiring volume</span>
          <select value={volume} onChange={(e) => setVolume(e.target.value)} className={inputClass}>
            <option value="" className="bg-ink">
              Hiring volume
            </option>
            {HIRING_VOLUMES.map((v) => (
              <option key={v} value={v} className="bg-ink">
                {v}
              </option>
            ))}
          </select>
        </label>
        <label className="block">
          <span className="sr-only">Roles you are hiring for</span>
          <input
            value={roles}
            onChange={(e) => setRoles(e.target.value)}
            placeholder="Roles (e.g. QA, backend)"
            className={inputClass}
          />
        </label>
      </div>

      <label className="mt-5 flex items-start gap-2.5 text-xs leading-relaxed text-white/50">
        <input
          type="checkbox"
          required
          checked={consent}
          onChange={(e) => setConsent(e.target.checked)}
          className="mt-0.5 h-4 w-4 shrink-0 accent-[var(--bj-trust)]"
        />
        <span>
          I agree to be contacted about hiring on BrowseJobs and accept the{" "}
          <Link href="/privacy-policy" className="text-trust hover:underline">
            privacy policy
          </Link>
          . Calls are recorded &amp; AI-monitored.
        </span>
      </label>

      <button
        disabled={busy || !consent}
        className="mt-6 w-full rounded-full bg-trust py-4 text-base font-semibold text-white shadow-[0_12px_40px_rgba(27,109,240,0.35)] transition-all hover:bg-deep disabled:opacity-45 disabled:shadow-none"
      >
        {busy ? "Sending…" : "Start the 6 free months"}
      </button>
      <p className="mono mt-3 text-center text-[11px] text-white/40">
        No card · No lock-in · Commercials in writing before anything is charged
      </p>
    </form>
  );
}

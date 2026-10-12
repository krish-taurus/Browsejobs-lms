"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { apiJson } from "@/lib/api";
import { ApLeadButton } from "@/components/ap/pages/ApLeadButton";
import "@/components/ap/pages/marketing.css";

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
      <section className="s-white pg-hero">
        <div className="wrap center">
          <p className="eyebrow">Daily market brief{brief ? ` · ${brief.date}` : ""}</p>
          <h1 className="h-hero">{brief?.headline ?? "Today's hiring signal"}</h1>
          <p className="lead">
            Who announced hiring, who cut, and where the capital is flowing — compiled from public news, every item with
            its source.
          </p>
        </div>
      </section>

      <section className="s-paper chapter brief-body">
        <div className="ap-narrow brief-stage">
          {/* The brief — blurred until registered. */}
          <div className={unlocked ? "" : "brief-locked"} aria-hidden={!unlocked}>
            {GROUPS.map(([key, label]) =>
              items[key].length === 0 ? null : (
                <div key={key} className="brief-group">
                  <p className="eyebrow-sm">{label}</p>
                  <ul>
                    {items[key].map((n, i) => (
                      <li key={`${n.sector}-${i}`} className="brief-item">
                        <div className="brief-item-head">
                          <span className="brief-item-title">
                            {n.headline ?? `${n.company ?? n.sector} · ${n.round}`}
                          </span>
                          <span className="brief-meta">
                            {n.hub} · {n.published_on}
                          </span>
                        </div>
                        {n.roles.length > 0 && (
                          <p className="brief-meta">
                            expect: {n.roles.join(" · ")}
                            {n.skills.length ? ` — ${n.skills.join(", ")}` : ""}
                          </p>
                        )}
                        <p className="brief-meta">
                          {n.source_url ? (
                            <a href={n.source_url} target="_blank" rel="noopener noreferrer" className="ap-link">
                              {n.source_name} ↗
                            </a>
                          ) : (
                            n.source_name
                          )}
                        </p>
                      </li>
                    ))}
                  </ul>
                </div>
              ),
            )}

            {brief !== null && brief.total === 0 && (
              <p className="brief-empty">Today&apos;s brief is being compiled — check back shortly.</p>
            )}
          </div>

          {/* Register-to-read gate */}
          {!unlocked && (
            <div className="brief-gate">
              <div className="pg-form">
                <p className="eyebrow-sm">Free · 10 seconds</p>
                <h2 className="h-card">Register to read today&apos;s brief</h2>
                <p className="body">One-time — the daily brief then stays open for you on this device.</p>
                <form onSubmit={register}>
                  <div className="field">
                    <input
                      id="brief-name"
                      placeholder=" "
                      value={form.name}
                      onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                      required
                    />
                    <label htmlFor="brief-name">Your name</label>
                  </div>
                  <div className="field">
                    <input
                      id="brief-phone"
                      placeholder=" "
                      inputMode="tel"
                      value={form.phone}
                      onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
                      required
                    />
                    <label htmlFor="brief-phone">WhatsApp number</label>
                  </div>
                  {error && <p className="pg-error">{error}</p>}
                  <button type="submit" disabled={busy} className="btn btn-primary">
                    {busy ? "Opening…" : "Read the brief"}
                  </button>
                  <p className="fine">By continuing you agree to receive the daily brief on WhatsApp. Unsubscribe anytime.</p>
                </form>
              </div>
            </div>
          )}
        </div>
      </section>

      {/* Masterclass recommendation — the conversion close. */}
      {unlocked && (
        <section className="s-black chapter pg-closing">
          <div className="wrap">
            <p className="eyebrow">While it&apos;s fresh</p>
            <h2 className="h-section">See how we turn this signal into a syllabus — live</h2>
            <p className="lead">
              The free masterclass shows the engine behind this brief, and how students train straight into the demand it
              finds.
            </p>
            <div className="cta-row">
              <ApLeadButton />
              <Link href="/masterclass" className="more">
                Watch the recording <span className="chev" aria-hidden="true">›</span>
              </Link>
            </div>
          </div>
        </section>
      )}
    </>
  );
}

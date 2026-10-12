"use client";

import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { ApLeadButton } from "@/components/ap/pages/ApLeadButton";
import { MASTERCLASS_RECORDING_URL } from "@/content/landing";
import "@/components/ap/pages/marketing.css";

/**
 * /masterclass — watch-anytime page. When a recording URL is configured it
 * embeds behind the same one-time register gate the brief uses (a watch is a
 * lead); until then the page sells the live session honestly. The live CTA
 * is always the loudest element.
 */

const UNLOCK_KEY = "bj-brief-unlocked"; // one registration unlocks brief + recording

export function MasterclassView() {
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
      <section className="s-white pg-hero mc-hero">
        <div className="wrap center">
          <p className="eyebrow">Free masterclass</p>
          <h1 className="h-hero">
            <span>See the method live</span> <span className="mc-sub">before you spend a rupee</span>
          </h1>
          <p className="lead">
            How the syllabus is reverse-engineered from real interviews — the session every student attends before
            deciding anything.
          </p>
          <div className="cta-row">
            <ApLeadButton />
          </div>
        </div>
      </section>

      <section className="s-paper chapter mc-recording">
        <div className="wrap center">
          {MASTERCLASS_RECORDING_URL === null ? (
            <div className="mc-note" data-reveal="">
              <p className="eyebrow-sm">Recording</p>
              <h2 className="h-tile">The latest recording lands here after each live session</h2>
              <p className="lead">
                Book the live session above — attendees get the recording first, inside their dashboard.
              </p>
            </div>
          ) : unlocked ? (
            <div data-reveal="">
              <div className="mc-video">
                <iframe
                  src={MASTERCLASS_RECORDING_URL}
                  title="BrowseJobs masterclass recording"
                  allow="accelerometer; autoplay; encrypted-media; picture-in-picture"
                  allowFullScreen
                />
              </div>
              <p className="fine">Prefer it live? The next session goes deeper and takes questions — book above.</p>
            </div>
          ) : (
            <div className="pg-form">
              <p className="eyebrow-sm">Free · 10 seconds</p>
              <h2 className="h-card">Register to watch the recording</h2>
              <form onSubmit={register}>
                <div className="field">
                  <input
                    id="mc-name"
                    placeholder=" "
                    value={form.name}
                    onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                    required
                  />
                  <label htmlFor="mc-name">Your name</label>
                </div>
                <div className="field">
                  <input
                    id="mc-phone"
                    placeholder=" "
                    inputMode="tel"
                    value={form.phone}
                    onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
                    required
                  />
                  <label htmlFor="mc-phone">WhatsApp number</label>
                </div>
                {error && <p className="pg-error">{error}</p>}
                <button type="submit" disabled={busy} className="btn btn-primary">
                  {busy ? "Opening…" : "Watch the recording"}
                </button>
              </form>
            </div>
          )}
        </div>
      </section>
    </>
  );
}

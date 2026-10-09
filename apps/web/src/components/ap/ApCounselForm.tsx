"use client";

import { useRef, useState, type FormEvent } from "react";
import { CALL_TIMES, COUNTRY_CODES } from "@/content/enquiries";

/**
 * The counselling call-back form in the approved Apple design, posting the
 * same payload as components/apple/EnquiryForm (type "counselling") to
 * /api/enquiries.
 */
export function ApCounselForm() {
  const [values, setValues] = useState({ name: "", email: "", country: "+91", phone: "", city: "", time: "", message: "", consent: false });
  const [honeypot, setHoneypot] = useState("");
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState("");
  const startedAt = useRef(Date.now());
  const set = (k: keyof typeof values) => (e: { target: { value: string } }) => setValues((v) => ({ ...v, [k]: e.target.value }));

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!e.currentTarget.reportValidity()) return;
    setBusy(true);
    setError("");
    const params = new URLSearchParams(window.location.search);
    const pick = (k: string) => params.get(k)?.slice(0, 120) ?? "";
    try {
      const res = await fetch("/api/enquiries", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({
          type: "counselling",
          name: values.name.trim(),
          email: values.email.trim(),
          phone: `${values.country}${values.phone.replace(/\D/g, "")}`,
          city: values.city.trim(),
          message: values.message.trim(),
          consent: true,
          preferred_time: values.time,
          referrer: document.referrer.slice(0, 500),
          landing_page: `${window.location.pathname}${window.location.search}`.slice(0, 500),
          form_started_at: startedAt.current,
          website: honeypot,
          utm_source: pick("utm_source"),
          utm_medium: pick("utm_medium"),
          utm_campaign: pick("utm_campaign"),
        }),
      });
      const payload = (await res.json().catch(() => null)) as { message?: string; errors?: Record<string, string[]> } | null;
      if (res.status === 201) {
        setDone(true);
        return;
      }
      if (res.status === 422 && payload?.errors) {
        setError(Object.values(payload.errors)[0]?.[0] ?? "Please check the form and try again.");
        return;
      }
      setError(res.status === 429 ? "Too many attempts. Please wait a minute and try again." : payload?.message || "We could not save that just now. Please try again.");
    } catch {
      setError("We could not save that just now. Please try again.");
    } finally {
      setBusy(false);
    }
  }

  if (done) {
    return (
      <div className="form-done">
        <svg viewBox="0 0 72 72" aria-hidden="true">
          <circle cx="36" cy="36" r="30" />
          <path d="M24 37l8 8 16-17" />
        </svg>
        <h3 className="h-card" tabIndex={-1}>
          Thank you.
        </h3>
        <p className="body">A BrowseJobs counsellor calls you back at the time you chose.</p>
      </div>
    );
  }

  return (
    <form className="fields" onSubmit={submit} noValidate={false}>
      <div className="field full">
        <input id="c-name" name="name" placeholder=" " autoComplete="name" required value={values.name} onChange={set("name")} />
        <label htmlFor="c-name">Full name</label>
      </div>
      <div className="field full">
        <input id="c-email" name="email" type="email" placeholder=" " autoComplete="email" required value={values.email} onChange={set("email")} />
        <label htmlFor="c-email">Email</label>
      </div>
      <div className="field full field-phone">
        <div className="field">
          <select id="c-cc" name="cc" aria-label="Country code" value={values.country} onChange={set("country")}>
            {COUNTRY_CODES.map((c) => (
              <option key={c.value} value={c.value}>
                {c.label}
              </option>
            ))}
          </select>
          <label htmlFor="c-cc">Country code</label>
        </div>
        <div className="field">
          <input id="c-phone" name="phone" type="tel" placeholder=" " autoComplete="tel-national" required value={values.phone} onChange={set("phone")} />
          <label htmlFor="c-phone">Phone</label>
        </div>
      </div>
      <div className="field">
        <input id="c-city" name="city" placeholder=" " autoComplete="address-level2" value={values.city} onChange={set("city")} />
        <label htmlFor="c-city">City</label>
      </div>
      <div className="field">
        <select id="c-time" name="time" required value={values.time} onChange={set("time")}>
          <option value="">Choose a time</option>
          {CALL_TIMES.map((t) => (
            <option key={t.value} value={t.value}>
              {t.label}
            </option>
          ))}
        </select>
        <label htmlFor="c-time">Best time to call</label>
      </div>
      <div className="field full">
        <textarea id="c-msg" name="message" placeholder=" " value={values.message} onChange={set("message")}></textarea>
        <label htmlFor="c-msg">Anything you want us to know (optional)</label>
      </div>
      <div className="hp" aria-hidden="true">
        <label htmlFor="c-hp">Leave this blank</label>
        <input id="c-hp" name="company_website" tabIndex={-1} autoComplete="off" value={honeypot} onChange={(e) => setHoneypot(e.target.value)} />
      </div>
      <label className="check full">
        <input id="c-consent" type="checkbox" required checked={values.consent} onChange={(e) => setValues((v) => ({ ...v, consent: e.target.checked }))} /> BrowseJobs may call
        or email me about this enquiry.
      </label>
      {error && (
        <p className="fine full" role="alert">
          {error}
        </p>
      )}
      <div className="form-actions full">
        <button className="btn btn-primary" type="submit" disabled={busy}>
          {busy ? "Sending…" : "Request a callback"}
        </button>
      </div>
    </form>
  );
}

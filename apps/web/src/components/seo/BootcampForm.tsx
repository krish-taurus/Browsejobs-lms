"use client";

import { useRef, useState, type FormEvent, type ReactNode } from "react";

type Errors = Partial<Record<"name" | "email" | "phone" | "organisation" | "experience" | "consent" | "form", string>>;

const control =
  "mt-1 w-full rounded-[10px] border border-line bg-white px-3 py-2 text-ink outline-none focus:border-trust";

/**
 * Free Python bootcamp lead. Posts into the existing enquiry inbox as type
 * "bootcamp". College or company is stored on company. Experience is the message.
 */
export function BootcampForm() {
  const startedAt = useRef(Date.now());
  const [honeypot, setHoneypot] = useState("");
  const [values, setValues] = useState({
    name: "",
    email: "",
    phone: "",
    organisation: "",
    experience: "",
    consent: false,
  });
  const [errors, setErrors] = useState<Errors>({});
  const [sending, setSending] = useState(false);
  const [done, setDone] = useState(false);

  function set<K extends keyof typeof values>(key: K, value: (typeof values)[K]) {
    setValues((current) => ({ ...current, [key]: value }));
  }

  function validate(): Errors {
    const next: Errors = {};
    if (values.name.trim().length < 2) next.name = "Enter your full name.";
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email.trim())) next.email = "Enter a valid email address.";
    const national = values.phone.replace(/\D/g, "");
    if (national.length < 8 || national.length > 14) next.phone = "Enter a phone number we can call.";
    if (values.organisation.trim().length < 2) next.organisation = "Enter your college or company.";
    if (values.experience.trim().length < 2) next.experience = "Tell us your experience. Fresher is a fine answer.";
    if (!values.consent) next.consent = "Please confirm we may contact you about this bootcamp.";
    return next;
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const next = validate();
    setErrors(next);
    if (Object.keys(next).length > 0) return;

    setSending(true);
    const digits = values.phone.replace(/\D/g, "");
    const body = {
      type: "bootcamp",
      name: values.name.trim(),
      email: values.email.trim(),
      phone: digits.startsWith("91") ? `+${digits}` : `+91${digits}`,
      company: values.organisation.trim(),
      message: values.experience.trim(),
      course_slug: "free-python-bootcamp",
      consent: true,
      landing_page: "/free-python-bootcamp",
      referrer: typeof document !== "undefined" ? document.referrer.slice(0, 500) : "",
      form_started_at: startedAt.current,
      website: honeypot,
    };

    try {
      const response = await fetch("/api/enquiries", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(body),
      });
      if (response.status === 201) {
        setDone(true);
        return;
      }
      const payload = (await response.json().catch(() => null)) as { message?: string; errors?: Record<string, string[]> } | null;
      if (response.status === 422 && payload?.errors) {
        const mapped: Errors = {};
        for (const [key, messages] of Object.entries(payload.errors)) {
          const field = key === "company" ? "organisation" : key === "message" ? "experience" : key;
          if (messages[0] && field in { name: 1, email: 1, phone: 1, organisation: 1, experience: 1, consent: 1, form: 1 }) {
            mapped[field as keyof Errors] = messages[0];
          }
        }
        setErrors(mapped);
        return;
      }
      setErrors({ form: payload?.message || "We could not save that just now. Please try again." });
    } catch {
      setErrors({ form: "We could not save that just now. Please try again." });
    } finally {
      setSending(false);
    }
  }

  if (done) {
    return (
      <div>
        <h2>Thank you.</h2>
        <p>We have your seat request. Someone from BrowseJobs will call you on the number you gave. The start date is still a placeholder until Dr Krish Bharggav sets it.</p>
      </div>
    );
  }

  return (
    <form onSubmit={onSubmit} noValidate className="ap-prose" aria-busy={sending}>
      <p className="fine">
        <label>
          Company website
          <input
            tabIndex={-1}
            autoComplete="off"
            value={honeypot}
            onChange={(event) => setHoneypot(event.target.value)}
            style={{ position: "absolute", left: "-9999px" }}
          />
        </label>
      </p>
      <Field id="bootcamp-name" label="Full name" error={errors.name}>
        <input id="bootcamp-name" className={control} autoComplete="name" value={values.name} onChange={(event) => set("name", event.target.value)} required />
      </Field>
      <Field id="bootcamp-email" label="Email" error={errors.email}>
        <input id="bootcamp-email" className={control} type="email" autoComplete="email" value={values.email} onChange={(event) => set("email", event.target.value)} required />
      </Field>
      <Field id="bootcamp-phone" label="Phone" error={errors.phone}>
        <input id="bootcamp-phone" className={control} type="tel" autoComplete="tel" inputMode="tel" value={values.phone} onChange={(event) => set("phone", event.target.value)} required />
      </Field>
      <Field id="bootcamp-org" label="College or company" error={errors.organisation}>
        <input id="bootcamp-org" className={control} value={values.organisation} onChange={(event) => set("organisation", event.target.value)} required />
      </Field>
      <Field id="bootcamp-experience" label="Experience" error={errors.experience}>
        <input id="bootcamp-experience" className={control} value={values.experience} onChange={(event) => set("experience", event.target.value)} required />
      </Field>
      <p>
        <label>
          <input type="checkbox" checked={values.consent} onChange={(event) => set("consent", event.target.checked)} /> BrowseJobs may call or email me about this bootcamp.
        </label>
        {errors.consent ? <span className="fine"> {errors.consent}</span> : null}
      </p>
      {errors.form ? <p>{errors.form}</p> : null}
      <p>
        <button type="submit" className="btn btn-primary" disabled={sending}>
          {sending ? "Sending…" : "Request a seat"}
        </button>
      </p>
    </form>
  );
}

function Field({ id, label, error, children }: { id: string; label: string; error?: string; children: ReactNode }) {
  return (
    <p>
      <label htmlFor={id}>{label}</label>
      <br />
      {children}
      {error ? (
        <span className="fine" id={`${id}-error`}>
          {" "}
          {error}
        </span>
      ) : null}
    </p>
  );
}

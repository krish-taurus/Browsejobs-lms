"use client";

import { useEffect, useId, useRef, useState, type FormEvent, type ReactNode } from "react";
import { useSearchParams } from "next/navigation";
import {
  CALL_TIMES,
  COMPANY_SIZES,
  COUNTRY_CODES,
  EMPLOYER_INTENTS,
  HIRING_TIMELINES,
  LEARNER_STATUSES,
  employerIntent,
  enquiryCourses,
  liveCourseSlug,
  type EmployerIntent,
} from "@/content/enquiries";

type EnquiryType = "employer" | "course";

type FieldErrors = Record<string, string>;

type Props = {
  type: EnquiryType;
  course?: string;
  intent?: EmployerIntent | null;
};

export function EmployerEnquiry() {
  const params = useSearchParams();
  return <EnquiryForm type="employer" intent={employerIntent(params.get("path") ?? undefined)} />;
}

export function CourseEnquiry() {
  const params = useSearchParams();
  return <EnquiryForm type="course" course={liveCourseSlug(params.get("course") ?? undefined)} />;
}

const control =
  "h-14 w-full rounded-[10px] border border-line bg-white px-4 text-[17px] text-ink outline-none transition-[border-color,box-shadow] duration-150 placeholder:text-muted focus:border-trust focus:ring-2 focus:ring-trust/25 aria-[invalid=true]:border-warn";

function trackGenerateLead(type: EnquiryType) {
  const win = window as Window & {
    gtag?: (command: string, name: string, params?: Record<string, string>) => void;
    clarity?: (command: string, value: string) => void;
  };
  win.gtag?.("event", "generate_lead", { lead_type: type });
  win.clarity?.("set", `lead_type_${type}`);
  win.clarity?.("event", "generate_lead");
}

function utm(): Record<string, string> {
  const params = new URLSearchParams(window.location.search);
  const pick = (key: string) => params.get(key)?.slice(0, 120) ?? "";
  return {
    utm_source: pick("utm_source"),
    utm_medium: pick("utm_medium"),
    utm_campaign: pick("utm_campaign"),
  };
}

export function EnquiryForm({ type, course = "", intent = null }: Props) {
  const startedAt = useRef(Date.now());
  const headingRef = useRef<HTMLHeadingElement>(null);
  const formId = useId();
  const [country, setCountry] = useState("+91");
  const [values, setValues] = useState({
    name: "",
    email: "",
    phone: "",
    company: "",
    company_size: "",
    roles: "",
    city: "",
    timeline: "",
    course_slug: course,
    learner_status: "",
    preferred_time: "",
    message: "",
    consent: false,
  });
  const [honeypot, setHoneypot] = useState("");
  const [errors, setErrors] = useState<FieldErrors>({});
  const [formError, setFormError] = useState("");
  const [sending, setSending] = useState(false);
  const [done, setDone] = useState(false);

  useEffect(() => {
    if (done) headingRef.current?.focus();
  }, [done]);

  function set<K extends keyof typeof values>(key: K, value: (typeof values)[K]) {
    setValues((current) => ({ ...current, [key]: value }));
    setErrors((current) => {
      if (!current[key]) return current;
      const next = { ...current };
      delete next[key];
      return next;
    });
  }

  function validate(): FieldErrors {
    const next: FieldErrors = {};
    if (values.name.trim().length < 2) next.name = "Enter your full name.";
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email.trim())) next.email = "Enter a valid email address.";
    const national = values.phone.replace(/\D/g, "");
    if (national.length < 6 || national.length > 14) next.phone = "Enter a phone number we can call.";
    if (values.city.trim().length < 2) next.city = type === "employer" ? "Enter a city, or Remote." : "Enter your city.";

    if (type === "employer") {
      if (values.company.trim().length < 2) next.company = "Enter the company name.";
      if (!values.company_size) next.company_size = "Choose a company size.";
      if (values.roles.trim().length < 2) next.roles = "Tell us the roles and how many people you are hiring.";
      if (!values.timeline) next.timeline = "Choose a hiring timeline.";
    } else {
      if (!values.course_slug) next.course_slug = "Choose a course.";
      if (!values.learner_status) next.learner_status = "Choose the option that fits you.";
      if (!values.preferred_time) next.preferred_time = "Choose a time we can call.";
    }

    if (!values.consent) next.consent = "Please confirm we may contact you about this enquiry.";
    return next;
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const next = validate();
    setErrors(next);
    setFormError("");
    const first = Object.keys(next)[0];
    if (first) {
      document.getElementById(`${formId}-${first}`)?.focus();
      return;
    }

    setSending(true);
    const digits = values.phone.replace(/\D/g, "");
    const attribution = utm();
    const body = {
      type,
      name: values.name.trim(),
      email: values.email.trim(),
      phone: `${country}${digits}`,
      city: values.city.trim(),
      message: values.message.trim(),
      consent: true,
      company: type === "employer" ? values.company.trim() : undefined,
      company_size: type === "employer" ? values.company_size : undefined,
      roles: type === "employer" ? values.roles.trim() : undefined,
      timeline: type === "employer" ? values.timeline : undefined,
      course_slug: type === "course" ? values.course_slug : undefined,
      learner_status: type === "course" ? values.learner_status : undefined,
      preferred_time: type === "course" ? values.preferred_time : undefined,
      referrer: document.referrer.slice(0, 500),
      landing_page: `${window.location.pathname}${window.location.search}`.slice(0, 500),
      form_started_at: startedAt.current,
      website: honeypot,
      ...attribution,
    };

    try {
      const response = await fetch("/api/enquiries", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(body),
      });
      const payload = (await response.json().catch(() => null)) as {
        message?: string;
        errors?: Record<string, string[]>;
      } | null;

      if (response.status === 201) {
        trackGenerateLead(type);
        setDone(true);
        return;
      }

      if (response.status === 422 && payload?.errors) {
        const mapped: FieldErrors = {};
        for (const [key, messages] of Object.entries(payload.errors)) {
          if (messages[0]) mapped[key] = messages[0];
        }
        setErrors(mapped);
        setFormError(mapped.form ?? "");
        const field = Object.keys(mapped).find((key) => key !== "form");
        if (field) document.getElementById(`${formId}-${field}`)?.focus();
        return;
      }

      if (response.status === 429) {
        setFormError("Too many attempts. Please wait a minute and try again.");
        return;
      }

      setFormError(payload?.message || "We could not save that just now. Please try again.");
    } catch {
      setFormError("We could not save that just now. Please try again.");
    } finally {
      setSending(false);
    }
  }

  return (
    <div className="min-h-[36rem]">
      {done ? (
        <div className="py-10 text-center">
          <h2 ref={headingRef} tabIndex={-1} className="apple-display text-[clamp(2.5rem,6vw,4rem)] outline-none">
            Thank you.
          </h2>
          <p className="mx-auto mt-4 max-w-[36rem] text-[19px] leading-snug text-ink2">
            We have your enquiry. Someone from BrowseJobs will call you on the number you gave.
          </p>
        </div>
      ) : (
        <form onSubmit={onSubmit} noValidate className="mx-auto max-w-[640px] space-y-7 text-left" aria-busy={sending}>
          {intent ? (
            <p className="text-[17px] text-ink2">{EMPLOYER_INTENTS[intent]}</p>
          ) : null}

          <Field id={`${formId}-name`} label="Full name" error={errors.name}>
            <input
              id={`${formId}-name`}
              className={control}
              autoComplete="name"
              value={values.name}
              onChange={(event) => set("name", event.target.value)}
              aria-invalid={errors.name ? true : undefined}
              aria-describedby={errors.name ? `${formId}-name-error` : undefined}
              required
            />
          </Field>

          <Field
            id={`${formId}-email`}
            label={type === "employer" ? "Work email" : "Email"}
            error={errors.email}
          >
            <input
              id={`${formId}-email`}
              type="email"
              inputMode="email"
              autoComplete="email"
              className={control}
              value={values.email}
              onChange={(event) => set("email", event.target.value)}
              aria-invalid={errors.email ? true : undefined}
              aria-describedby={errors.email ? `${formId}-email-error` : undefined}
              required
            />
          </Field>

          <div className="grid gap-4 sm:grid-cols-[11.5rem_1fr]">
            <Field id={`${formId}-country`} label="Country code" error="">
              <select
                id={`${formId}-country`}
                className={control}
                autoComplete="tel-country-code"
                value={country}
                onChange={(event) => setCountry(event.target.value)}
              >
                {COUNTRY_CODES.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </Field>
            <Field id={`${formId}-phone`} label="Phone" error={errors.phone}>
              <input
                id={`${formId}-phone`}
                type="tel"
                inputMode="tel"
                autoComplete="tel-national"
                className={control}
                placeholder="98400 11111"
                value={values.phone}
                onChange={(event) => set("phone", event.target.value)}
                aria-invalid={errors.phone ? true : undefined}
                aria-describedby={errors.phone ? `${formId}-phone-error` : undefined}
                required
              />
            </Field>
          </div>

          {type === "employer" ? (
            <>
              <Field id={`${formId}-company`} label="Company" error={errors.company}>
                <input
                  id={`${formId}-company`}
                  className={control}
                  autoComplete="organization"
                  value={values.company}
                  onChange={(event) => set("company", event.target.value)}
                  aria-invalid={errors.company ? true : undefined}
                  aria-describedby={errors.company ? `${formId}-company-error` : undefined}
                  required
                />
              </Field>
              <Field id={`${formId}-company_size`} label="Company size" error={errors.company_size}>
                <select
                  id={`${formId}-company_size`}
                  className={control}
                  value={values.company_size}
                  onChange={(event) => set("company_size", event.target.value)}
                  aria-invalid={errors.company_size ? true : undefined}
                  aria-describedby={errors.company_size ? `${formId}-company_size-error` : undefined}
                  required
                >
                  <option value="">Choose a size</option>
                  {COMPANY_SIZES.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </Field>
              <Field id={`${formId}-roles`} label="Roles and number of hires" error={errors.roles}>
                <input
                  id={`${formId}-roles`}
                  className={control}
                  value={values.roles}
                  onChange={(event) => set("roles", event.target.value)}
                  aria-invalid={errors.roles ? true : undefined}
                  aria-describedby={errors.roles ? `${formId}-roles-error` : undefined}
                  required
                />
              </Field>
              <Field id={`${formId}-city`} label="City or remote" error={errors.city}>
                <input
                  id={`${formId}-city`}
                  className={control}
                  autoComplete="address-level2"
                  placeholder="Bengaluru, or Remote"
                  value={values.city}
                  onChange={(event) => set("city", event.target.value)}
                  aria-invalid={errors.city ? true : undefined}
                  aria-describedby={errors.city ? `${formId}-city-error` : undefined}
                  required
                />
              </Field>
              <Field id={`${formId}-timeline`} label="Hiring timeline" error={errors.timeline}>
                <select
                  id={`${formId}-timeline`}
                  className={control}
                  value={values.timeline}
                  onChange={(event) => set("timeline", event.target.value)}
                  aria-invalid={errors.timeline ? true : undefined}
                  aria-describedby={errors.timeline ? `${formId}-timeline-error` : undefined}
                  required
                >
                  <option value="">Choose a timeline</option>
                  {HIRING_TIMELINES.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </Field>
            </>
          ) : (
            <>
              <Field id={`${formId}-course_slug`} label="Course of interest" error={errors.course_slug}>
                <select
                  id={`${formId}-course_slug`}
                  className={control}
                  value={values.course_slug}
                  onChange={(event) => set("course_slug", event.target.value)}
                  aria-invalid={errors.course_slug ? true : undefined}
                  aria-describedby={errors.course_slug ? `${formId}-course_slug-error` : undefined}
                  required
                >
                  <option value="">Choose a course</option>
                  {enquiryCourses.map((option) => (
                    <option key={option.slug} value={option.slug}>
                      {option.name}
                    </option>
                  ))}
                </select>
              </Field>
              <Field id={`${formId}-learner_status`} label="Where you are now" error={errors.learner_status}>
                <select
                  id={`${formId}-learner_status`}
                  className={control}
                  value={values.learner_status}
                  onChange={(event) => set("learner_status", event.target.value)}
                  aria-invalid={errors.learner_status ? true : undefined}
                  aria-describedby={errors.learner_status ? `${formId}-learner_status-error` : undefined}
                  required
                >
                  <option value="">Choose one</option>
                  {LEARNER_STATUSES.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </Field>
              <Field id={`${formId}-city`} label="City" error={errors.city}>
                <input
                  id={`${formId}-city`}
                  className={control}
                  autoComplete="address-level2"
                  value={values.city}
                  onChange={(event) => set("city", event.target.value)}
                  aria-invalid={errors.city ? true : undefined}
                  aria-describedby={errors.city ? `${formId}-city-error` : undefined}
                  required
                />
              </Field>
              <Field id={`${formId}-preferred_time`} label="Preferred time to call" error={errors.preferred_time}>
                <select
                  id={`${formId}-preferred_time`}
                  className={control}
                  value={values.preferred_time}
                  onChange={(event) => set("preferred_time", event.target.value)}
                  aria-invalid={errors.preferred_time ? true : undefined}
                  aria-describedby={errors.preferred_time ? `${formId}-preferred_time-error` : undefined}
                  required
                >
                  <option value="">Choose a time</option>
                  {CALL_TIMES.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </Field>
            </>
          )}

          <Field id={`${formId}-message`} label="Message, if you want to add one" error={errors.message}>
            <textarea
              id={`${formId}-message`}
              className={`${control} h-32 resize-y py-3`}
              value={values.message}
              maxLength={2000}
              onChange={(event) => set("message", event.target.value)}
              aria-invalid={errors.message ? true : undefined}
              aria-describedby={errors.message ? `${formId}-message-error` : undefined}
            />
          </Field>

          <div className="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
            <label htmlFor={`${formId}-hp`}>Leave this blank</label>
            <input
              id={`${formId}-hp`}
              tabIndex={-1}
              autoComplete="off"
              value={honeypot}
              onChange={(event) => setHoneypot(event.target.value)}
            />
          </div>

          <div>
            <label className="flex min-h-11 items-start gap-3 text-[17px] leading-snug text-ink">
              <input
                id={`${formId}-consent`}
                type="checkbox"
                className="mt-1 h-5 w-5 rounded border-line text-trust focus:ring-trust"
                checked={values.consent}
                onChange={(event) => set("consent", event.target.checked)}
                aria-invalid={errors.consent ? true : undefined}
                aria-describedby={errors.consent ? `${formId}-consent-error` : undefined}
                required
              />
              <span>BrowseJobs may call or email me about this enquiry.</span>
            </label>
            <p id={`${formId}-consent-error`} role={errors.consent ? "alert" : undefined} className="min-h-5 text-sm text-warn">
              {errors.consent ?? ""}
            </p>
          </div>

          {formError ? (
            <p role="alert" className="text-[15px] text-warn">
              {formError}
            </p>
          ) : null}

          <button type="submit" className="apple-pill w-full border-0 disabled:cursor-wait disabled:opacity-60" disabled={sending}>
            {sending ? "Sending…" : "Send enquiry"}
          </button>
        </form>
      )}
    </div>
  );
}

function Field({
  id,
  label,
  error,
  children,
}: {
  id: string;
  label: string;
  error?: string;
  children: ReactNode;
}) {
  return (
    <div className="space-y-2">
      <label htmlFor={id} className="block text-[21px] font-semibold tracking-[-0.02em] text-ink">
        {label}
      </label>
      {children}
      <p id={`${id}-error`} role={error ? "alert" : undefined} className="min-h-5 text-sm text-warn">
        {error ?? ""}
      </p>
    </div>
  );
}

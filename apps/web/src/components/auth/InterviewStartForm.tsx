"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { GoogleButton } from "@/components/auth/GoogleButton";
import { ApiError, apiJson } from "@/lib/api";
import { safeNextPath } from "@/lib/safeNext";

const AFTER_AUTH = "/interview";

type Step = "phone" | "details" | "code";

/**
 * The shortest start the existing auth allows.
 * A phone number, then a code. A new number also needs a name and consent,
 * because registration will not create an account without them.
 * Google appears only when the API already has it configured.
 */
export function InterviewStartForm({
  next = AFTER_AUTH,
  id = "interview-start",
}: {
  next?: string;
  id?: string;
}) {
  const router = useRouter();
  const destination = safeNextPath(next) ?? AFTER_AUTH;
  const [step, setStep] = useState<Step>("phone");
  const [phone, setPhone] = useState("");
  const [name, setName] = useState("");
  const [consent, setConsent] = useState(false);
  const [code, setCode] = useState("");
  const [isNew, setIsNew] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const inputCls =
    "w-full rounded-[10px] border border-white/15 bg-white/5 px-4 py-3 text-fg outline-none placeholder:text-muted focus:border-trust";

  function fail(err: unknown, fallback: string) {
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : fallback);
  }

  async function requestLogin(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await apiJson("/api/v1/auth/otp/request", {
        method: "POST",
        body: JSON.stringify({ identifier: phone }),
      });
      setIsNew(false);
      setStep("code");
    } catch (err) {
      const message = err instanceof ApiError ? (err.firstError ?? "") : "";
      if (err instanceof ApiError && err.status === 422 && /create an account/i.test(message)) {
        setIsNew(true);
        setError(null);
        setStep("details");
      } else {
        fail(err, "Could not send a code.");
      }
    } finally {
      setBusy(false);
    }
  }

  async function requestRegister(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await apiJson("/api/v1/auth/register/request", {
        method: "POST",
        body: JSON.stringify({ name, phone, consent, channel: "sms" }),
      });
      setStep("code");
    } catch (err) {
      fail(err, "Could not send a code.");
    } finally {
      setBusy(false);
    }
  }

  async function verify(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      if (isNew) {
        await apiJson("/api/v1/auth/register/verify", {
          method: "POST",
          body: JSON.stringify({ name, phone, code, consent, channel: "sms" }),
        });
      } else {
        await apiJson("/api/v1/auth/otp/verify", {
          method: "POST",
          body: JSON.stringify({ identifier: phone, code }),
        });
      }
      router.push(destination);
    } catch (err) {
      fail(err, "That code did not work.");
      setBusy(false);
    }
  }

  return (
    <div id={id} className="mt-5 max-w-md scroll-mt-28 md:mt-8">
      {error && (
        <p className="mb-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn" role="alert">
          {error}
        </p>
      )}

      {step === "phone" && (
        <form onSubmit={requestLogin} className="space-y-3">
          <label className="block">
            <span className="mono text-[11px] uppercase tracking-[0.14em] text-muted">Phone number</span>
            <input
              autoFocus
              required
              name="phone"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              inputMode="tel"
              autoComplete="tel"
              minLength={8}
              placeholder="10-digit mobile number"
              aria-label="Phone number"
              className={`${inputCls} mt-2`}
            />
          </label>
          <button
            disabled={busy}
            className="inline-flex w-full items-center justify-center rounded-full bg-trust px-7 py-3.5 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep disabled:opacity-50"
          >
            {busy ? "Checking…" : "Take your free AI interview"}
          </button>
          <p className="text-sm text-muted">Free. One code by SMS. No card.</p>
        </form>
      )}

      {step === "details" && (
        <form onSubmit={requestRegister} className="space-y-3">
          <p className="text-sm text-muted">
            New number. Your name and a yes to the terms, then the code. The interview is next.
          </p>
          <label className="block">
            <span className="mono text-[11px] uppercase tracking-[0.14em] text-muted">Your name</span>
            <input
              autoFocus
              required
              value={name}
              onChange={(e) => setName(e.target.value)}
              autoComplete="name"
              placeholder="Your name"
              aria-label="Your name"
              className={`${inputCls} mt-2`}
            />
          </label>
          <label className="flex items-start gap-2 text-xs leading-relaxed text-muted">
            <input
              type="checkbox"
              checked={consent}
              onChange={(e) => setConsent(e.target.checked)}
              className="mt-0.5 accent-trust"
              required
            />
            <span>
              I agree to the{" "}
              <Link href="/privacy-policy" className="text-sky hover:underline">
                privacy policy
              </Link>{" "}
              and{" "}
              <Link href="/terms" className="text-sky hover:underline">
                terms
              </Link>
              , including that my learning activity is monitored to personalise coaching.
            </span>
          </label>
          <button
            disabled={busy || !name.trim() || !consent}
            className="inline-flex w-full items-center justify-center rounded-full bg-trust px-7 py-3.5 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep disabled:opacity-50"
          >
            {busy ? "Sending…" : "Send code"}
          </button>
          <button type="button" onClick={() => setStep("phone")} className="w-full text-sm text-muted hover:text-fg">
            Use a different phone
          </button>
        </form>
      )}

      {step === "code" && (
        <form onSubmit={verify} className="space-y-3">
          <p className="text-sm text-muted">We sent a 6-digit code to {phone}.</p>
          <label className="block">
            <span className="mono text-[11px] uppercase tracking-[0.14em] text-muted">Code</span>
            <input
              autoFocus
              required
              value={code}
              onChange={(e) => setCode(e.target.value)}
              inputMode="numeric"
              autoComplete="one-time-code"
              placeholder="6-digit code"
              aria-label="6-digit code"
              className="mono mt-2 w-full rounded-[10px] border border-white/15 bg-white/5 px-4 py-3 text-center text-lg tracking-[0.4em] text-fg outline-none focus:border-trust"
            />
          </label>
          <button
            disabled={busy || code.trim().length < 4}
            className="inline-flex w-full items-center justify-center rounded-full bg-trust px-7 py-3.5 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep disabled:opacity-50"
          >
            {busy ? "Verifying…" : "Verify and start"}
          </button>
          <button
            type="button"
            onClick={() => {
              setCode("");
              setStep(isNew ? "details" : "phone");
            }}
            className="w-full text-sm text-muted hover:text-fg"
          >
            {isNew ? "Edit my details" : "Use a different phone"}
          </button>
        </form>
      )}

      {step === "phone" && <GoogleButton next={destination} />}
    </div>
  );
}

"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { GoogleButton } from "@/components/auth/GoogleButton";
import { AuthAlert, AuthField, AuthScreen } from "@/components/auth/AuthScreen";
import { safeNextPath } from "@/lib/safeNext";

function afterLogin(): string {
  return safeNextPath(new URLSearchParams(window.location.search).get("next")) ?? "/dashboard";
}

export default function RegisterPage() {
  const router = useRouter();
  const [step, setStep] = useState<"details" | "code">("details");
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [email, setEmail] = useState("");
  const [code, setCode] = useState("");
  const [consent, setConsent] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [nextPath, setNextPath] = useState<string | undefined>(undefined);

  useEffect(() => {
    setNextPath(safeNextPath(new URLSearchParams(window.location.search).get("next")) ?? undefined);
  }, []);

  const payload = () => ({
    name,
    phone,
    email: email || null,
    channel: email ? "email" : "sms",
    consent,
  });

  async function requestOtp(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await apiJson("/api/v1/auth/register/request", {
        method: "POST",
        body: JSON.stringify(payload()),
      });
      setStep("code");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  async function verify(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await apiJson("/api/v1/auth/register/verify", {
        method: "POST",
        body: JSON.stringify({ ...payload(), code }),
      });
      router.push(afterLogin());
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <AuthScreen
      portal="student"
      eyebrow="Job seekers"
      title={step === "details" ? "Create your account." : "Enter your code."}
      lede={step === "details" ? "One-time code. No password to remember." : `We sent a 6-digit code to ${email || phone}.`}
      links={[
        { href: "/employer", label: "Employers" },
        { href: "/student", label: "Sign in" },
      ]}
      below={
        <>
          Already have an account? <Link href="/student">Sign in</Link>
        </>
      }
    >
      {error && <AuthAlert>{error}</AuthAlert>}

      {step === "details" ? (
        <>
          <form onSubmit={requestOtp} className="bj-auth-form">
            <AuthField id="name" label="Your name" autoFocus required value={name} onChange={(e) => setName(e.target.value)} placeholder="Your name" autoComplete="name" />
            <AuthField id="phone" label="WhatsApp number" required value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="WhatsApp number" inputMode="tel" autoComplete="tel" />
            <AuthField
              id="email"
              label="Email (optional)"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              type="email"
              placeholder="Email (optional — code goes here if given)"
              autoComplete="email"
            />
            <p className="bj-auth-hint">If you add an email, the code goes there instead of your phone.</p>
            <label className="bj-auth-consent">
              <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} />
              <span>
                I agree to the <Link href="/privacy-policy">privacy policy</Link> and <Link href="/terms">terms</Link>, including that my learning
                activity is monitored to personalise coaching.
              </span>
            </label>
            <button className="bj-auth-btn" disabled={busy || !name || !phone || !consent}>
              {busy ? "Sending…" : "Send code"}
            </button>
          </form>
          <GoogleButton next={nextPath} />
        </>
      ) : (
        <form onSubmit={verify} className="bj-auth-form">
          <AuthField
            id="code"
            label="6-digit code"
            className="is-code"
            autoFocus
            value={code}
            onChange={(e) => setCode(e.target.value)}
            inputMode="numeric"
            autoComplete="one-time-code"
            placeholder="6-digit code"
          />
          <button className="bj-auth-btn" disabled={busy || code.length < 4}>
            {busy ? "Creating account…" : "Verify & create account"}
          </button>
          <button type="button" className="bj-auth-textbtn" onClick={() => setStep("details")}>
            Edit my details
          </button>
        </form>
      )}
    </AuthScreen>
  );
}

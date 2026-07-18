"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { GoogleButton } from "@/components/auth/GoogleButton";
import { Wordmark } from "@/components/brand/Wordmark";

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
      router.push("/dashboard");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  const inputCls =
    "w-full rounded-[10px] border border-line bg-paper px-4 py-3 text-ink outline-none focus:border-trust focus:ring-2 focus:ring-trust/25";

  return (
    <div className="grid min-h-screen place-items-center px-5 py-10">
      <div className="w-full max-w-sm">
        <Link href="/" className="flex justify-center" aria-label="BrowseJobs home">
          <Wordmark />
        </Link>

        <div className="mt-8 rounded-[14px] border border-line bg-white p-7 shadow-soft">
          <p className="kicker text-trust">Student portal</p>
          <h1 className="display mt-2 text-2xl text-ink">
            {step === "details" ? "Create your account" : "Enter your code"}
          </h1>
          <p className="mt-1 text-sm text-muted">
            {step === "details"
              ? "One-time code — no password to remember."
              : `We sent a 6-digit code to ${email || phone}.`}
          </p>

          {error && (
            <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>
          )}

          {step === "details" ? (
            <>
              <form onSubmit={requestOtp} className="mt-6 space-y-3">
                <input autoFocus required value={name} onChange={(e) => setName(e.target.value)} placeholder="Your name" className={inputCls} />
                <input required value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="WhatsApp number" inputMode="tel" className={inputCls} />
                <input value={email} onChange={(e) => setEmail(e.target.value)} type="email" placeholder="Email (optional — code goes here if given)" className={inputCls} />
                <label className="flex items-start gap-2 text-xs text-muted">
                  <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} className="mt-0.5 accent-trust" />
                  <span>
                    I agree to the{" "}
                    <Link href="/privacy-policy" className="text-trust hover:underline">privacy policy</Link> and{" "}
                    <Link href="/terms" className="text-trust hover:underline">terms</Link>, including that my learning
                    activity is monitored to personalise coaching.
                  </span>
                </label>
                <button
                  disabled={busy || !name || !phone || !consent}
                  className="w-full rounded-full bg-trust py-3 font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
                >
                  {busy ? "Sending…" : "Send code"}
                </button>
              </form>
              <GoogleButton />
            </>
          ) : (
            <form onSubmit={verify} className="mt-6 space-y-4">
              <input
                autoFocus
                value={code}
                onChange={(e) => setCode(e.target.value)}
                inputMode="numeric"
                placeholder="6-digit code"
                className="mono w-full rounded-[10px] border border-line bg-paper px-4 py-3 text-center text-lg tracking-[0.4em] text-ink outline-none focus:border-trust"
              />
              <button
                disabled={busy || code.length < 4}
                className="w-full rounded-full bg-trust py-3 font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
              >
                {busy ? "Creating account…" : "Verify & create account"}
              </button>
              <button type="button" onClick={() => setStep("details")} className="w-full text-sm text-muted hover:text-ink">
                Edit my details
              </button>
            </form>
          )}
        </div>

        <p className="mt-6 text-center text-xs text-muted">
          Already have an account?{" "}
          <Link href="/student" className="text-trust hover:underline">
            Sign in
          </Link>
        </p>
      </div>
    </div>
  );
}

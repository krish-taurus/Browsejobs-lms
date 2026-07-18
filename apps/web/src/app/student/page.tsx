"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { GoogleButton } from "@/components/auth/GoogleButton";
import { Wordmark } from "@/components/brand/Wordmark";

export default function StudentLogin() {
  const router = useRouter();
  const [step, setStep] = useState<"identifier" | "code">("identifier");
  const [identifier, setIdentifier] = useState("");
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function requestOtp(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await apiJson("/api/v1/auth/otp/request", {
        method: "POST",
        body: JSON.stringify({ identifier }),
      });
      setStep("code");
    } catch (err) {
      setError(err instanceof ApiError ? err.firstError ?? err.message : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  async function verifyOtp(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await apiJson("/api/v1/auth/otp/verify", {
        method: "POST",
        body: JSON.stringify({ identifier, code }),
      });
      router.push("/dashboard");
    } catch (err) {
      setError(err instanceof ApiError ? err.firstError ?? err.message : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="grid min-h-screen place-items-center px-5">
      <div className="w-full max-w-sm">
        <Link href="/" className="flex justify-center" aria-label="BrowseJobs home">
          <Wordmark />
        </Link>

        <div className="mt-8 rounded-2xl border border-line bg-white p-7 shadow-sm">
          <p className="kicker text-trust">Student portal</p>
          <h1 className="display mt-2 text-2xl text-ink">
            {step === "identifier" ? "Sign in" : "Enter your code"}
          </h1>
          <p className="mt-1 text-sm text-muted">
            {step === "identifier"
              ? "We'll send a one-time code to your phone or email."
              : `We sent a 6-digit code to ${identifier}.`}
          </p>

          {error && (
            <p className="mt-4 rounded-lg bg-warn/10 px-3 py-2 text-sm text-warn">
              {error}
            </p>
          )}

          {step === "identifier" ? (
            <>
              <form onSubmit={requestOtp} className="mt-6 space-y-4">
                <input
                  autoFocus
                  value={identifier}
                  onChange={(e) => setIdentifier(e.target.value)}
                  placeholder="Phone or email"
                  className="w-full rounded-lg border border-line bg-paper px-4 py-3 text-ink outline-none focus:border-trust"
                />
                <button
                  disabled={busy || !identifier}
                  className="w-full rounded-full bg-trust py-3 font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
                >
                  {busy ? "Sending…" : "Send code"}
                </button>
              </form>
              <GoogleButton />
            </>
          ) : (
            <form onSubmit={verifyOtp} className="mt-6 space-y-4">
              <input
                autoFocus
                value={code}
                onChange={(e) => setCode(e.target.value)}
                inputMode="numeric"
                placeholder="6-digit code"
                className="mono w-full rounded-lg border border-line bg-paper px-4 py-3 text-center text-lg tracking-[0.4em] text-ink outline-none focus:border-trust"
              />
              <button
                disabled={busy || code.length < 4}
                className="w-full rounded-full bg-trust py-3 font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
              >
                {busy ? "Verifying…" : "Verify & continue"}
              </button>
              <button
                type="button"
                onClick={() => setStep("identifier")}
                className="w-full text-sm text-muted hover:text-ink"
              >
                Use a different phone / email
              </button>
            </form>
          )}
        </div>

        <p className="mt-6 text-center text-xs text-muted">
          New here?{" "}
          <Link href="/register" className="text-trust hover:underline">
            Create an account
          </Link>
          {" · "}Staff?{" "}
          <Link href="/admin" className="text-trust hover:underline">
            Sign in here
          </Link>
        </p>
      </div>
    </div>
  );
}

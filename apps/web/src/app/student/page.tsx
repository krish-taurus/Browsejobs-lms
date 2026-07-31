"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { GoogleButton } from "@/components/auth/GoogleButton";
import { Wordmark } from "@/components/brand/Wordmark";
import { DevOtpModal } from "@/components/auth/DevOtpModal";

type Mode = "otp" | "password";

export default function StudentLogin() {
  const router = useRouter();
  const [mode, setMode] = useState<Mode>("otp");
  const [step, setStep] = useState<"identifier" | "code">("identifier");
  const [identifier, setIdentifier] = useState("");
  const [code, setCode] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [devCode, setDevCode] = useState<string | null>(null);

  function switchMode(next: Mode) {
    setMode(next);
    setStep("identifier");
    setError(null);
    setCode("");
    setPassword("");
  }

  async function requestOtp(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const res = await apiJson<{ status: string; debug_code?: string }>(
        "/api/v1/auth/otp/request",
        {
          method: "POST",
          body: JSON.stringify({ identifier }),
        },
      );
      setStep("code");
      // Present only while the API has no SMS/email integration configured
      // (local dev) — disappears on its own once a gateway is set up.
      setDevCode(res.debug_code ?? null);
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

  async function loginWithPassword(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await apiJson("/api/v1/auth/login", {
        method: "POST",
        body: JSON.stringify({ identifier, password }),
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
            {mode === "password"
              ? "Sign in"
              : step === "identifier"
                ? "Sign in"
                : "Enter your code"}
          </h1>
          <p className="mt-1 text-sm text-muted">
            {mode === "password"
              ? "Use the login and password from your batch welcome message."
              : step === "identifier"
                ? "We'll send a one-time code to your phone or email."
                : `We sent a 6-digit code to ${identifier} — check WhatsApp, and your email inbox if you have one on file.`}
          </p>

          {error && (
            <p className="mt-4 rounded-lg bg-warn/10 px-3 py-2 text-sm text-warn">
              {error}
            </p>
          )}

          {mode === "password" ? (
            <>
              <form onSubmit={loginWithPassword} className="mt-6 space-y-4">
                <input
                  autoFocus
                  value={identifier}
                  onChange={(e) => setIdentifier(e.target.value)}
                  placeholder="Phone or email"
                  className="w-full rounded-lg border border-line bg-paper px-4 py-3 text-ink outline-none focus:border-trust"
                />
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Password"
                  className="w-full rounded-lg border border-line bg-paper px-4 py-3 text-ink outline-none focus:border-trust"
                />
                <button
                  disabled={busy || !identifier || !password}
                  className="w-full rounded-full bg-trust py-3 font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
                >
                  {busy ? "Signing in…" : "Sign in"}
                </button>
              </form>
              <button
                type="button"
                onClick={() => switchMode("otp")}
                className="mt-4 w-full text-sm text-muted hover:text-ink"
              >
                Sign in with a one-time code instead
              </button>
            </>
          ) : step === "identifier" ? (
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
              <button
                type="button"
                onClick={() => switchMode("password")}
                className="mt-4 w-full text-sm text-muted hover:text-ink"
              >
                Have a password? Sign in with it
              </button>
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

        <DevOtpModal
          code={devCode}
          onUse={(c) => {
            setCode(c);
            setDevCode(null);
          }}
          onClose={() => setDevCode(null)}
        />

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

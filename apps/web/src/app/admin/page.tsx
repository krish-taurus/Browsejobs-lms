"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import type { AuthUser } from "@/lib/auth";
import { Wordmark } from "@/components/brand/Wordmark";

type LoginResponse =
  | { status: "authenticated"; user: AuthUser }
  | { status: "2fa_required" };

export default function StaffLoginPage() {
  const router = useRouter();
  const [step, setStep] = useState<"credentials" | "code">("credentials");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function login(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const res = await apiJson<LoginResponse>("/api/v1/auth/staff/login", {
        method: "POST",
        body: JSON.stringify({ email, password }),
      });
      if (res.status === "2fa_required") {
        setStep("code");
      } else {
        router.push("/admin/curriculum");
      }
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
      await apiJson("/api/v1/auth/staff/2fa", {
        method: "POST",
        body: JSON.stringify({ email, code }),
      });
      router.push("/admin/curriculum");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  const inputCls =
    "w-full rounded-[10px] border border-line bg-paper px-4 py-3 text-ink outline-none focus:border-trust focus:ring-2 focus:ring-trust/25";

  return (
    <div className="grid min-h-screen place-items-center px-5">
      <div className="w-full max-w-sm">
        <Link href="/" className="flex justify-center" aria-label="BrowseJobs home">
          <Wordmark />
        </Link>

        <div className="mt-8 rounded-[14px] border border-line bg-white p-7 shadow-soft">
          <p className="kicker text-trust">Staff portal</p>
          <h1 className="display mt-2 text-2xl text-ink">
            {step === "credentials" ? "Sign in" : "Two-factor code"}
          </h1>
          <p className="mt-1 text-sm text-muted">
            {step === "credentials"
              ? "Email and password. A 2FA code follows if enabled."
              : `We emailed a 6-digit code to ${email}.`}
          </p>

          {error && (
            <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>
          )}

          {step === "credentials" ? (
            <form onSubmit={login} className="mt-6 space-y-3">
              <input autoFocus required type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="Work email" className={inputCls} />
              <input required type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="Password" className={inputCls} />
              <button
                disabled={busy || !email || !password}
                className="w-full rounded-full bg-trust py-3 font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
              >
                {busy ? "Signing in…" : "Sign in"}
              </button>
            </form>
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
                {busy ? "Verifying…" : "Verify & sign in"}
              </button>
            </form>
          )}
        </div>

        <p className="mt-6 text-center text-xs text-muted">
          Student?{" "}
          <Link href="/student" className="text-trust hover:underline">
            Sign in here
          </Link>
        </p>
      </div>
    </div>
  );
}

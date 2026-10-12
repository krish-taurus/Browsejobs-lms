"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import type { AuthUser } from "@/lib/auth";
import { AuthAlert, AuthField, AuthScreen } from "@/components/auth/AuthScreen";

type LoginResponse =
  | { status: "authenticated"; user: AuthUser }
  | { status: "2fa_required" };

export default function StaffLoginPage() {
  const router = useRouter();
  const [step, setStep] = useState<"credentials" | "code">("credentials");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
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

  return (
    <AuthScreen
      portal="staff"
      eyebrow="Staff portal"
      title={step === "credentials" ? "Sign in." : "Two-factor code."}
      lede={step === "credentials" ? "Email and password. A 2FA code follows if enabled." : `We emailed a 6-digit code to ${email}.`}
      links={[
        { href: "/student", label: "Job seekers" },
        { href: "/employer", label: "Employers" },
      ]}
      below={
        <>
          Student? <Link href="/student">Sign in here</Link>
        </>
      }
    >
      {error && <AuthAlert>{error}</AuthAlert>}

      {step === "credentials" ? (
        <form onSubmit={login} className="bj-auth-form">
          <AuthField
            id="staff-email"
            label="Work email"
            autoFocus
            required
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="Work email"
            autoComplete="username"
          />
          <AuthField
            id="staff-password"
            label="Password"
            required
            type={showPassword ? "text" : "password"}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="Password"
            autoComplete="current-password"
            trailing={
              <button
                type="button"
                className="bj-auth-trailing"
                onClick={() => setShowPassword((v) => !v)}
                aria-label={showPassword ? "Hide password" : "Show password"}
              >
                {showPassword ? "Hide" : "Show"}
              </button>
            }
          />
          <button className="bj-auth-btn" disabled={busy || !email || !password}>
            {busy ? "Signing in…" : "Sign in"}
          </button>
        </form>
      ) : (
        <form onSubmit={verify} className="bj-auth-form">
          <AuthField
            id="staff-code"
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
            {busy ? "Verifying…" : "Verify & sign in"}
          </button>
        </form>
      )}
    </AuthScreen>
  );
}

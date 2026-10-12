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

export default function StudentLogin() {
  const router = useRouter();
  const [step, setStep] = useState<"identifier" | "code">("identifier");
  const [identifier, setIdentifier] = useState("");
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [nextPath, setNextPath] = useState<string | undefined>(undefined);

  useEffect(() => {
    setNextPath(safeNextPath(new URLSearchParams(window.location.search).get("next")) ?? undefined);
  }, []);

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
      router.push(afterLogin());
    } catch (err) {
      setError(err instanceof ApiError ? err.firstError ?? err.message : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <AuthScreen
      portal="student"
      eyebrow="Job seekers"
      title={step === "identifier" ? "Sign in." : "Enter your code."}
      lede={step === "identifier" ? "We'll send a one-time code to your phone." : `We sent a 6-digit code to ${identifier}.`}
      links={[
        { href: "/employer", label: "Employers" },
        { href: "/register", label: "Create account" },
      ]}
      below={
        <>
          New here? <Link href="/register">Create an account</Link>
          <br />
          Hiring? <Link href="/employer">Employer sign in</Link> · Staff? <Link href="/admin">Sign in here</Link>
        </>
      }
    >
      {error && <AuthAlert>{error}</AuthAlert>}

      {step === "identifier" ? (
        <>
          <form onSubmit={requestOtp} className="bj-auth-form">
            <AuthField
              id="identifier"
              label="Phone number"
              autoFocus
              value={identifier}
              onChange={(e) => setIdentifier(e.target.value)}
              placeholder="Phone number"
              inputMode="tel"
              autoComplete="tel"
            />
            <button className="bj-auth-btn" disabled={busy || !identifier}>
              {busy ? "Sending…" : "Send code"}
            </button>
          </form>
          <GoogleButton next={nextPath} />
        </>
      ) : (
        <form onSubmit={verifyOtp} className="bj-auth-form">
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
            {busy ? "Verifying…" : "Verify & continue"}
          </button>
          <button type="button" className="bj-auth-textbtn" onClick={() => setStep("identifier")}>
            Use a different phone
          </button>
        </form>
      )}
    </AuthScreen>
  );
}

"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import type { AuthUser } from "@/lib/auth";
import { employerApi } from "@/lib/employer";
import { AuthAlert, AuthField, AuthScreen } from "@/components/auth/AuthScreen";

type LoginResponse =
  | { status: "authenticated"; user: AuthUser }
  | { status: "2fa_required" };

export default function EmployerLoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [suspended, setSuspended] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function login(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSuspended(null);
    setBusy(true);
    try {
      const res = await apiJson<LoginResponse>("/api/v1/auth/employer/login", {
        method: "POST",
        body: JSON.stringify({ email, password }),
      });
      if (res.status === "authenticated") {
        // Someone who belongs to several companies should still get in when
        // only one of them is paused, so this only stops people whose every
        // workspace is suspended.
        const { data: workspaces } = await employerApi.workspaces();
        const usable = workspaces.filter((w) => w.status !== "suspended");

        if (workspaces.length > 0 && usable.length === 0) {
          setSuspended(workspaces[0].name);

          return;
        }

        // Land on Taurus AI, not the (view-only) Dashboard — talking to it is
        // meant to be the ordinary way in, per the Taurus AI kit's own nav
        // placement (Sept 2026).
        router.push("/employer/taurus-ai");
      } else {
        setError("Two-factor is enabled on this account — check your email, then sign in via the staff portal flow.");
      }
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <AuthScreen
      portal="employer"
      eyebrow="Employers"
      title={suspended ? `${suspended} is deactivated.` : "Sign in to your hiring workspace."}
      links={[
        { href: "/employers", label: "For employers" },
        { href: "/student", label: "Job seekers" },
      ]}
      below={
        suspended ? undefined : (
          <>
            Looking for work? <Link href="/student">Job seeker sign in</Link>
            <br />
            New to BrowseJobs? <Link href="/employers/enquire">Talk to us about hiring</Link>
          </>
        )
      }
    >
      {suspended ? (
        <div className="bj-auth-notice">
          <p>
            Your team&rsquo;s access to BrowseJobs has been paused. Nothing has been deleted — your job postings, applicants and history are all
            still here.
          </p>
          <p>Please connect with BrowseJobs admin to restore access.</p>
          <a href="mailto:support@browsejobs.ai?subject=Reactivate%20our%20workspace" className="bj-auth-btn">
            Contact BrowseJobs admin
          </a>
          <button type="button" className="bj-auth-textbtn" onClick={() => setSuspended(null)}>
            Use a different account
          </button>
        </div>
      ) : (
        <>
          {error && <AuthAlert>{error}</AuthAlert>}
          <form onSubmit={login} className="bj-auth-form">
            <AuthField
              id="email"
              label="Work email"
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoComplete="email"
              placeholder="you@company.com"
            />
            <AuthField
              id="password"
              label="Password"
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
              placeholder="Your password"
            />
            <button className="bj-auth-btn" disabled={busy}>
              {busy ? "Signing in…" : "Sign in"}
            </button>
          </form>
          <p className="bj-auth-fine">Every candidate you receive is pre-interviewed, graded, and video-verified.</p>
        </>
      )}
    </AuthScreen>
  );
}

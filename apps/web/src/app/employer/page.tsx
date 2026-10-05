"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import type { AuthUser } from "@/lib/auth";
import { employerApi } from "@/lib/employer";
import { Wordmark } from "@/components/brand/Wordmark";

type LoginResponse =
  | { status: "authenticated"; user: AuthUser }
  | { status: "2fa_required" };

/** Input styling for the dark card: legible text, visible placeholder, real focus ring. */
const FIELD =
  "mt-1 w-full rounded-input border border-white/[0.14] bg-white/[0.05] px-3 py-2 text-sm text-white outline-none transition-shadow placeholder:text-white/30 focus:border-[#4d8ef7] focus:ring-4 focus:ring-[#4d8ef7]/25";

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
    // The portal is dark, so this page is too. It previously inherited a
    // near-black card from a theme sweep while keeping dark text and
    // untouched inputs, which left the heading, the labels and the typed
    // password invisible against it.
    <div className="grid min-h-screen place-items-center bg-[#05070d] px-4">
      <div className="w-full max-w-sm">
        <div className="mb-6 flex justify-center"><Wordmark tone="dark" /></div>

        {suspended ? (
          <div className="rounded-panel border border-[#3a1c1c] bg-[#1a0f0f] p-8 text-center">
            <span className="mx-auto flex size-12 items-center justify-center rounded-full bg-[#2a1414] text-2xl">🔒</span>
            <h1 className="display mt-4 text-xl text-white">{suspended} is deactivated</h1>
            <p className="mt-3 text-sm leading-relaxed text-white/60">
              Your team&rsquo;s access to BrowseJobs has been paused. Nothing has been deleted — your
              job postings, applicants and history are all still here.
            </p>
            <p className="mt-3 text-sm leading-relaxed text-white/60">
              Please connect with BrowseJobs admin to restore access.
            </p>
            <a
              href="mailto:support@browsejobs.ai?subject=Reactivate%20our%20workspace"
              className="mt-5 inline-block rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-[#0a0f1c] transition hover:bg-white/90"
            >
              Contact BrowseJobs admin
            </a>
            <button
              type="button"
              onClick={() => setSuspended(null)}
              className="mt-4 block w-full text-sm font-medium text-white/40 transition hover:text-white/70"
            >
              Use a different account
            </button>
          </div>
        ) : (
        <form
          onSubmit={login}
          className="rounded-panel border border-white/[0.10] bg-[#0a0f1c] p-8 shadow-[0_30px_90px_rgba(0,0,0,0.5)]"
        >
          <p className="mono text-[11px] uppercase tracking-widest text-[#4d8ef7]">Employer portal</p>
          <h1 className="display mt-2 text-xl text-white">Sign in to your hiring workspace</h1>

          <label className="mt-6 block text-sm font-medium text-white/80" htmlFor="email">Work email</label>
          <input
            id="email"
            type="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className={FIELD}
            autoComplete="email"
            placeholder="you@company.com"
          />

          <label className="mt-4 block text-sm font-medium text-white/80" htmlFor="password">Password</label>
          <input
            id="password"
            type="password"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className={FIELD}
            autoComplete="current-password"
            placeholder="Your password"
          />

          {error && (
            <p className="mt-3 rounded-xl bg-[#e05561]/15 px-3 py-2 text-sm text-[#fca5a5]">{error}</p>
          )}

          <button
            disabled={busy}
            className="mt-6 w-full rounded-input bg-[#4d8ef7] px-4 py-2.5 text-sm font-semibold text-white shadow-[0_12px_36px_rgba(77,142,247,0.35)] transition-shadow hover:shadow-[0_16px_46px_rgba(77,142,247,0.5)] disabled:opacity-50 disabled:shadow-none"
          >
            {busy ? "Signing in…" : "Sign in"}
          </button>

          <p className="mt-4 text-center text-xs text-white/45">
            Every candidate you receive is pre-interviewed, graded, and video-verified.
          </p>
          <p className="mt-3 text-center text-xs text-white/45">
            Looking for work?{" "}
            <a href="/student" className="font-semibold text-[#4d8ef7] hover:underline">
              Job seeker sign in
            </a>
          </p>
        </form>
        )}
      </div>
    </div>
  );
}

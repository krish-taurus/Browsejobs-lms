"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import type { AuthUser } from "@/lib/auth";
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
  const [busy, setBusy] = useState(false);

  async function login(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const res = await apiJson<LoginResponse>("/api/v1/auth/employer/login", {
        method: "POST",
        body: JSON.stringify({ email, password }),
      });
      if (res.status === "authenticated") {
        router.push("/employer/dashboard");
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
      </div>
    </div>
  );
}

"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import type { AuthUser } from "@/lib/auth";
import { Wordmark } from "@/components/brand/Wordmark";

type LoginResponse =
  | { status: "authenticated"; user: AuthUser }
  | { status: "2fa_required" };

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
    <div className="grid min-h-screen place-items-center bg-paper px-4">
      <div className="w-full max-w-sm">
        <div className="mb-6 flex justify-center"><Wordmark /></div>
        <form onSubmit={login} className="rounded-panel border border-line bg-[#0a0f1c] p-8 shadow-soft">
          <p className="mono text-[11px] uppercase tracking-widest text-trust">Employer portal</p>
          <h1 className="display mt-2 text-xl text-ink">Sign in to your hiring workspace</h1>
          <label className="mt-6 block text-sm font-medium text-ink" htmlFor="email">Work email</label>
          <input
            id="email"
            type="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="mt-1 w-full rounded-input border border-line px-3 py-2 text-sm"
            autoComplete="email"
            placeholder="Work email"
          />
          <label className="mt-4 block text-sm font-medium text-ink" htmlFor="password">Password</label>
          <input
            id="password"
            type="password"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="mt-1 w-full rounded-input border border-line px-3 py-2 text-sm"
            autoComplete="current-password"
            placeholder="Password"
          />
          {error && <p className="mt-3 text-sm text-warn">{error}</p>}
          <button
            disabled={busy}
            className="mt-6 w-full rounded-input bg-trust px-4 py-2.5 text-sm font-semibold text-white shadow-soft disabled:opacity-50"
          >
            {busy ? "Signing in…" : "Sign in"}
          </button>
          <p className="mt-4 text-center text-xs text-muted">
            Every candidate you receive is pre-interviewed, graded, and video-verified.
          </p>
        </form>
      </div>
    </div>
  );
}

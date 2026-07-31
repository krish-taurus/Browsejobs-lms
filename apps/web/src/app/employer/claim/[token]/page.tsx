"use client";

import { use, useState } from "react";
import { useRouter } from "next/navigation";
import { ApiError, apiJson } from "@/lib/api";
import { Wordmark } from "@/components/brand/Wordmark";

/**
 * Where an admin-onboarded employer sets their own password (PRD-E F1).
 *
 * BrowseJobs creates the account without a usable credential, so this is the
 * one and only way in for a new workspace owner. The link is single-use and
 * expiring; on success the session is already established, so there is no
 * login form to bounce through afterwards.
 */

const FIELD =
  "mt-1 w-full rounded-input border border-white/[0.14] bg-white/[0.05] px-3 py-2 text-sm text-white outline-none transition-shadow placeholder:text-white/30 focus:border-[#4d8ef7] focus:ring-4 focus:ring-[#4d8ef7]/25";

const MIN_LENGTH = 12;

export default function ClaimInvitePage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = use(params);
  const router = useRouter();
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const tooShort = password !== "" && password.length < MIN_LENGTH;
  const mismatch = confirm !== "" && confirm !== password;

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await apiJson("/api/v1/employer/invites/claim", {
        method: "POST",
        body: JSON.stringify({ token, password, password_confirmation: confirm }),
      });
      router.push("/employer/dashboard");
    } catch (err) {
      setError(
        err instanceof ApiError
          ? (err.firstError ?? err.message)
          : "That link could not be used. Ask BrowseJobs for a fresh one.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="grid min-h-screen place-items-center bg-[#05070d] px-4">
      <div className="w-full max-w-sm">
        <div className="mb-6 flex justify-center">
          <Wordmark tone="dark" />
        </div>

        <form
          onSubmit={submit}
          className="rounded-panel border border-white/[0.10] bg-[#0a0f1c] p-8 shadow-[0_30px_90px_rgba(0,0,0,0.5)]"
        >
          <p className="mono text-[11px] uppercase tracking-widest text-[#4d8ef7]">Employer portal</p>
          <h1 className="display mt-2 text-xl text-white">Set your password</h1>
          <p className="mt-2 text-sm leading-relaxed text-white/55">
            Your workspace is ready. Choose a password — nobody at BrowseJobs sees it, and this link
            works only once.
          </p>

          <label className="mt-6 block text-sm font-medium text-white/80" htmlFor="password">
            Password
          </label>
          <input
            id="password"
            type="password"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className={FIELD}
            autoComplete="new-password"
            placeholder={`At least ${MIN_LENGTH} characters`}
          />
          {/* Said up front rather than after a rejected submit: this account
              can see every applicant's contact details. */}
          <p className="mt-1.5 text-[12px] text-white/40">
            At least {MIN_LENGTH} characters. This login can see every applicant to your roles.
          </p>

          <label className="mt-4 block text-sm font-medium text-white/80" htmlFor="confirm">
            Confirm password
          </label>
          <input
            id="confirm"
            type="password"
            required
            value={confirm}
            onChange={(e) => setConfirm(e.target.value)}
            className={FIELD}
            autoComplete="new-password"
            placeholder="Type it again"
          />

          {tooShort && <p className="mt-2 text-[13px] text-[#e8bf63]">A bit longer, please.</p>}
          {mismatch && <p className="mt-2 text-[13px] text-[#e8bf63]">These do not match yet.</p>}

          {error && (
            <p className="mt-3 rounded-xl bg-[#e05561]/15 px-3 py-2 text-sm text-[#fca5a5]">{error}</p>
          )}

          <button
            disabled={busy || password.length < MIN_LENGTH || password !== confirm}
            className="mt-6 w-full rounded-input bg-[#4d8ef7] px-4 py-2.5 text-sm font-semibold text-white shadow-[0_12px_36px_rgba(77,142,247,0.35)] transition-shadow hover:shadow-[0_16px_46px_rgba(77,142,247,0.5)] disabled:opacity-50 disabled:shadow-none"
          >
            {busy ? "Setting up…" : "Set password and continue"}
          </button>

          <p className="mt-4 text-center text-xs text-white/45">
            Already set yours?{" "}
            <a href="/employer" className="font-semibold text-[#4d8ef7] hover:underline">
              Sign in
            </a>
          </p>
        </form>
      </div>
    </div>
  );
}

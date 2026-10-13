"use client";

import Link from "next/link";
import { use, useState } from "react";
import { useRouter } from "next/navigation";
import { ApiError, apiJson } from "@/lib/api";
import { AuthAlert, AuthField, AuthScreen } from "@/components/auth/AuthScreen";
import { TelLink, WhatsAppLink } from "@/components/contact/PhoneLinks";
import { EMPLOYER_PHONE } from "@/content/phones";

/**
 * Where an admin-onboarded employer sets their own password (PRD-E F1).
 *
 * BrowseJobs creates the account without a usable credential, so this is the
 * one and only way in for a new workspace owner. The link is single-use and
 * expiring; on success the session is already established, so there is no
 * login form to bounce through afterwards.
 */

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
    <AuthScreen
      portal="employer"
      eyebrow="Employers"
      title="Set your password."
      lede="Your workspace is ready. Choose a password — nobody at BrowseJobs sees it, and this link works only once."
      below={
        <>
          Already set yours? <Link href="/employer">Sign in</Link>
          <br />
          Hiring line · <TelLink phone={EMPLOYER_PHONE} /> · <WhatsAppLink phone={EMPLOYER_PHONE} />
        </>
      }
    >
      <form onSubmit={submit} className="bj-auth-form">
        <AuthField
          id="password"
          label="Password"
          type="password"
          required
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          autoComplete="new-password"
          placeholder={`At least ${MIN_LENGTH} characters`}
        />
        {/* Said up front rather than after a rejected submit: this account
            can see every applicant's contact details. */}
        <p className="bj-auth-hint">At least {MIN_LENGTH} characters. This login can see every applicant to your roles.</p>
        <AuthField
          id="confirm"
          label="Confirm password"
          type="password"
          required
          value={confirm}
          onChange={(e) => setConfirm(e.target.value)}
          autoComplete="new-password"
          placeholder="Type it again"
        />
        {tooShort && <p className="bj-auth-hint bj-auth-hint--warn">A bit longer, please.</p>}
        {mismatch && <p className="bj-auth-hint bj-auth-hint--warn">These do not match yet.</p>}
        {error && <AuthAlert>{error}</AuthAlert>}
        <button className="bj-auth-btn" disabled={busy || password.length < MIN_LENGTH || password !== confirm}>
          {busy ? "Setting up…" : "Set password and continue"}
        </button>
      </form>
    </AuthScreen>
  );
}

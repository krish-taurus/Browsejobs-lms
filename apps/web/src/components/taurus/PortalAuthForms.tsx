"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent, type InputHTMLAttributes, type ReactNode } from "react";
import "@/components/ap/pages/taurus-ui.css";
import { taurusDisplayFont } from "@/components/ap/pages/taurus-font";
import { ApiError, apiJson } from "@/lib/api";

/** The Taurus mark, shared by the sign-in pages and the portal bar. */
export function TaurusMark({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 40 40" aria-hidden="true" fill="none" stroke="currentColor" className={className}>
      <circle cx="20" cy="20" r="18" strokeWidth="1.3" opacity=".9" />
      <circle cx="20" cy="20" r="13" strokeWidth="1" strokeDasharray="2.5 2.5" opacity=".55" />
      <path d="M12.5 14h15L20 27.5z" strokeWidth="1.8" strokeLinejoin="round" />
      <circle cx="20" cy="18.2" r="1.7" fill="currentColor" stroke="none" />
    </svg>
  );
}

function Frame({ title, lede, children }: { title: string; lede: string; children: ReactNode }) {
  return (
    <div className={`tx-ap tx-auth ${taurusDisplayFont.variable}`}>
      <header className="tx-auth-top">
        <Link href="/taurusai" className="tx-wordmark" aria-label="Taurus AI by BrowseJobs">
          <b>BrowseJobs</b>
          <span>Taurus</span>
        </Link>
        <Link href="/taurusai" className="tx-auth-link">
          What is Taurus?
        </Link>
      </header>
      <main className="tx-auth-main">
        <div className="tx-auth-card">
          <span className="tx-auth-mark">
            <TaurusMark />
          </span>
          <h1>{title}</h1>
          <p className="tx-sub">{lede}</p>
          {children}
        </div>
      </main>
    </div>
  );
}

const message = (e: unknown, fallback: string) => (e instanceof ApiError ? (e.firstError ?? e.message) : fallback);

/** A floating-label field: the label sits in the box and moves up once there's text. */
function Field({
  id,
  label,
  ...input
}: { id: string; label: string } & InputHTMLAttributes<HTMLInputElement>) {
  return (
    <div className="tx-float">
      <input id={id} placeholder=" " {...input} />
      <label htmlFor={id}>{label}</label>
    </div>
  );
}

export function TaurusLoginForm() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await apiJson("/api/v1/auth/taurus/login", { method: "POST", body: JSON.stringify({ email, password }) });
      router.push("/taurusai/app");
    } catch (err) {
      setError(message(err, "Couldn't sign you in. Try again."));
      setBusy(false);
    }
  }

  return (
    <Frame title="Sign in to Taurus." lede="Your command centre: your agents, your keys, nobody else's.">
      <form onSubmit={submit} className="tx-auth-form">
        <Field id="taurus-email" label="Email" type="email" required autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} />
        <Field
          id="taurus-password"
          label="Password"
          type="password"
          required
          autoComplete="current-password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
        />
        {error && (
          <p className="tx-note tx-note--error" role="alert">
            {error}
          </p>
        )}
        <button type="submit" disabled={busy} className="tx-btn tx-btn-primary tx-btn-lg tx-btn-block">
          {busy ? "Signing in…" : "Sign in"}
        </button>
      </form>
      <p className="tx-auth-fine">
        No account yet? Taurus workspaces are set up by invitation. <Link href="/taurusai">See what Taurus does</Link>.
      </p>
      <p className="tx-auth-fine">
        Accounts with two-factor sign-in can&apos;t sign in here. Use the <Link href="/admin">BrowseJobs admin sign-in</Link> instead.
      </p>
    </Frame>
  );
}

export function TaurusClaimForm({ token }: { token: string }) {
  const router = useRouter();
  const [name, setName] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: FormEvent) {
    e.preventDefault();
    if (password !== confirm) {
      setError("The two passwords don't match.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await apiJson("/api/v1/auth/taurus/claim", {
        method: "POST",
        body: JSON.stringify({ token, name, password, password_confirmation: confirm }),
      });
      router.push("/taurusai/app");
    } catch (err) {
      setError(message(err, "That invite didn't work. Ask for a fresh link."));
      setBusy(false);
    }
  }

  return (
    <Frame title="Join your Taurus workspace." lede="Set your name and a password. You'll land straight on your command centre.">
      <form onSubmit={submit} className="tx-auth-form">
        <Field id="claim-name" label="Your name" required autoComplete="name" value={name} onChange={(e) => setName(e.target.value)} />
        <Field
          id="claim-password"
          label="Password (at least 10 characters)"
          type="password"
          required
          minLength={10}
          autoComplete="new-password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
        />
        <Field
          id="claim-confirm"
          label="Confirm password"
          type="password"
          required
          minLength={10}
          autoComplete="new-password"
          value={confirm}
          onChange={(e) => setConfirm(e.target.value)}
        />
        {error && (
          <p className="tx-note tx-note--error" role="alert">
            {error}
          </p>
        )}
        <button type="submit" disabled={busy} className="tx-btn tx-btn-primary tx-btn-lg tx-btn-block">
          {busy ? "Setting up…" : "Join workspace"}
        </button>
      </form>
      <p className="tx-auth-fine">This invite link works once and expires. If it doesn&apos;t work, ask the person who invited you for a fresh one.</p>
    </Frame>
  );
}

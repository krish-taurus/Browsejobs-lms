"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent, type InputHTMLAttributes, type ReactNode } from "react";
import { AuthAlert, AuthField, AuthScreen } from "@/components/auth/AuthScreen";
import { ApiError, apiJson } from "@/lib/api";

function Frame({ title, lede, children }: { title: string; lede: string; children: ReactNode }) {
  return (
    <AuthScreen
      portal="taurus"
      eyebrow="Taurus AI"
      title={title}
      lede={lede}
      links={[
        { href: "/taurusai", label: "What is Taurus?" },
        { href: "/admin", label: "Admin sign-in" },
      ]}
    >
      {children}
    </AuthScreen>
  );
}

const message = (e: unknown, fallback: string) => (e instanceof ApiError ? (e.firstError ?? e.message) : fallback);

/** A floating-label field (shared with the other sign-in pages). */
function Field(props: { id: string; label: string } & InputHTMLAttributes<HTMLInputElement>) {
  return <AuthField {...props} />;
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
      <form onSubmit={submit} className="bj-auth-form">
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
        {error && <AuthAlert>{error}</AuthAlert>}
        <button type="submit" disabled={busy} className="bj-auth-btn">
          {busy ? "Signing in…" : "Sign in"}
        </button>
      </form>
      <p className="bj-auth-fine">
        No account yet? Taurus workspaces are set up by invitation. <Link href="/taurusai">See what Taurus does</Link>.
      </p>
      <p className="bj-auth-fine">
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
      <form onSubmit={submit} className="bj-auth-form">
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
        {error && <AuthAlert>{error}</AuthAlert>}
        <button type="submit" disabled={busy} className="bj-auth-btn">
          {busy ? "Setting up…" : "Join workspace"}
        </button>
      </form>
      <p className="bj-auth-fine">This invite link works once and expires. If it doesn&apos;t work, ask the person who invited you for a fresh one.</p>
    </Frame>
  );
}

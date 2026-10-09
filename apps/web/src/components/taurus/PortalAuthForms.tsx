"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent, type ReactNode } from "react";
import { ApiError, apiJson } from "@/lib/api";

const inputCls = "w-full rounded-[10px] border border-white/15 bg-white/5 px-3 py-2.5 text-sm text-white outline-none placeholder:text-white/35 focus:border-sky";

function Frame({ title, lede, children }: { title: string; lede: string; children: ReactNode }) {
  return (
    <main className="grid min-h-screen place-items-center bg-ink px-5 py-16 text-white">
      <div className="w-full max-w-sm">
        <Link href="/taurusai" className="display text-lg tracking-[0.3em] text-white/90">
          TAURUS
        </Link>
        <h1 className="display mt-8 text-3xl">{title}</h1>
        <p className="mt-2 text-sm text-white/60">{lede}</p>
        {children}
      </div>
    </main>
  );
}

const message = (e: unknown, fallback: string) => (e instanceof ApiError ? (e.firstError ?? e.message) : fallback);

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
    <Frame title="Sign in to Taurus" lede="Your command centre: your agents, your keys, nobody else's.">
      <form onSubmit={submit} className="mt-8 space-y-3">
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-white/70">Email</span>
          <input id="taurus-email" type="email" required autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} className={inputCls} />
        </label>
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-white/70">Password</span>
          <input id="taurus-password" type="password" required autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} className={inputCls} />
        </label>
        {error && <p className="rounded-[10px] bg-warn/15 px-3 py-2 text-sm text-white" role="alert">{error}</p>}
        <button type="submit" disabled={busy} className="w-full rounded-full bg-trust px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">
          {busy ? "Signing in…" : "Sign in"}
        </button>
      </form>
      <p className="mt-6 text-xs text-white/45">
        No account yet? Taurus workspaces are set up by invitation.{" "}
        <Link href="/taurusai" className="text-white/80 underline-offset-2 hover:underline">
          See what Taurus does
        </Link>
        .
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
    <Frame title="Join your Taurus workspace" lede="Set your name and a password. You'll land straight on your command centre.">
      <form onSubmit={submit} className="mt-8 space-y-3">
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-white/70">Your name</span>
          <input id="claim-name" required autoComplete="name" value={name} onChange={(e) => setName(e.target.value)} className={inputCls} />
        </label>
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-white/70">Password (at least 10 characters)</span>
          <input id="claim-password" type="password" required minLength={10} autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} className={inputCls} />
        </label>
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-white/70">Confirm password</span>
          <input id="claim-confirm" type="password" required minLength={10} autoComplete="new-password" value={confirm} onChange={(e) => setConfirm(e.target.value)} className={inputCls} />
        </label>
        {error && <p className="rounded-[10px] bg-warn/15 px-3 py-2 text-sm text-white" role="alert">{error}</p>}
        <button type="submit" disabled={busy} className="w-full rounded-full bg-trust px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">
          {busy ? "Setting up…" : "Join workspace"}
        </button>
      </form>
    </Frame>
  );
}

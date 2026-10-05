"use client";

import { useState } from "react";
import { ApiError } from "@/lib/api";
import { employerApi, type EmployerRole } from "@/lib/employer";
import { ROLE_HINTS, ROLE_LABELS, INVITABLE_ROLES } from "./roles";

/**
 * Same submission path, validation and post-success refresh the page
 * already had (team/page.tsx's `invite()`) — only the layer above the
 * fetch call changed. Required email, optional name/WhatsApp, role limited
 * to what an owner may actually hand out (never "owner" itself — see
 * RolesPanel's footnote, same rule, same reason).
 */
export function InviteTeammateForm({
  workspaceId,
  workspaceName,
  onInvited,
}: {
  workspaceId: number;
  workspaceName: string;
  onInvited: () => void;
}) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [whatsapp, setWhatsapp] = useState("");
  const [role, setRole] = useState<EmployerRole>("recruiter");
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    setMessage(null);
    try {
      await employerApi.invite(workspaceId, {
        email,
        role,
        name: name.trim() || undefined,
        whatsapp: whatsapp.trim() || undefined,
      });
      setMessage(`Invite sent to ${email}. The link is single-use and expires in 7 days.`);
      setName("");
      setEmail("");
      setWhatsapp("");
      onInvited();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not send the invite.");
    } finally {
      setBusy(false);
    }
  }

  const fieldClass =
    "mt-1.5 h-11 w-full rounded-xl border bg-white px-3.5 text-sm outline-none transition-shadow placeholder:text-[color:var(--bj-dash-muted)]/70 focus:ring-2 focus:ring-[var(--bj-dash-focus)]/30 focus:border-[var(--bj-dash-focus)]";
  const fieldStyle = { borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" } as const;

  return (
    <div className="rounded-[var(--bj-dash-radius)] border bg-white p-5 sm:p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
      <p className="font-mono text-[11px] font-semibold uppercase tracking-widest" style={{ color: "var(--bj-dash-primary)" }}>
        Invite a teammate
      </p>
      <h2 className="bj-dash-serif mt-1.5" style={{ fontSize: "clamp(1.5rem, 2.6vw, 1.75rem)", color: "var(--bj-dash-ink)" }}>
        Add someone to {workspaceName}
      </h2>

      <form onSubmit={submit} className="mt-5" noValidate>
        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label htmlFor="invite-name" className="text-sm font-medium" style={{ color: "var(--bj-dash-ink)" }}>
              Name <span style={{ color: "var(--bj-dash-muted)" }}>(optional)</span>
            </label>
            <input
              id="invite-name"
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              maxLength={150}
              placeholder="e.g. Priya Sharma"
              className={fieldClass}
              style={fieldStyle}
            />
          </div>
          <div>
            <label htmlFor="invite-email" className="text-sm font-medium" style={{ color: "var(--bj-dash-ink)" }}>
              Work email
            </label>
            <input
              id="invite-email"
              type="email"
              required
              aria-required="true"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="colleague@company.com"
              className={fieldClass}
              style={fieldStyle}
            />
          </div>
          <div>
            <label htmlFor="invite-whatsapp" className="text-sm font-medium" style={{ color: "var(--bj-dash-ink)" }}>
              WhatsApp <span style={{ color: "var(--bj-dash-muted)" }}>(optional)</span>
            </label>
            <input
              id="invite-whatsapp"
              type="tel"
              value={whatsapp}
              onChange={(e) => setWhatsapp(e.target.value)}
              maxLength={20}
              placeholder="+91 98765 43210"
              className={fieldClass}
              style={fieldStyle}
            />
          </div>
          <div>
            <label htmlFor="invite-role" className="text-sm font-medium" style={{ color: "var(--bj-dash-ink)" }}>
              Role
            </label>
            <select
              id="invite-role"
              value={role}
              onChange={(e) => setRole(e.target.value as EmployerRole)}
              className={fieldClass}
              style={fieldStyle}
            >
              {INVITABLE_ROLES.map((r) => (
                <option key={r} value={r}>{ROLE_LABELS[r]}</option>
              ))}
            </select>
          </div>
        </div>

        <p
          className="mt-4 flex items-start gap-2 rounded-xl px-3.5 py-2.5 text-sm"
          style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-ink)" }}
        >
          <span className="font-semibold">{ROLE_LABELS[role]}</span>
          {" — "}
          {ROLE_HINTS[role].charAt(0).toLowerCase() + ROLE_HINTS[role].slice(1)}.
        </p>

        <p className="mt-3 text-xs leading-relaxed" style={{ color: "var(--bj-dash-muted)" }}>
          Name and WhatsApp are for your reference. Teammates choose their own name when they join.
        </p>

        <div className="mt-4 flex flex-wrap items-center gap-3">
          <button
            type="submit"
            disabled={busy}
            className="flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold text-white transition disabled:opacity-50"
            style={{ background: "var(--bj-dash-primary)" }}
          >
            {busy ? "Sending…" : "Send invite"}
            {!busy && <span aria-hidden>→</span>}
          </button>
          <span className="text-xs" style={{ color: "var(--bj-dash-muted)" }}>Invitations expire in 7 days.</span>
        </div>

        <div aria-live="polite">
          {message && (
            <p className="mt-3 rounded-xl px-4 py-2.5 text-sm" style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}>
              {message}
            </p>
          )}
          {error && (
            <p className="mt-3 rounded-xl bg-[#fde8e9] px-4 py-2.5 text-sm text-[#a5352f]" role="alert">
              {error}
            </p>
          )}
        </div>
      </form>
    </div>
  );
}

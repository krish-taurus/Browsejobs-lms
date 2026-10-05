"use client";

import { useState } from "react";
import { ApiError } from "@/lib/api";
import { employerApi, type InviteRow, type MemberRow } from "@/lib/employer";
import { ROLE_LABELS } from "./roles";

/** Initials avatar — no image uploads in the workspace yet. */
function Avatar({ name }: { name: string }) {
  const initials = name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() ?? "")
    .join("");

  return (
    <span
      aria-hidden
      className="grid size-11 shrink-0 place-items-center rounded-full text-sm font-semibold"
      style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}
    >
      {initials || "—"}
    </span>
  );
}

/** How long until (or since) an invite's link stops working. */
function expiryLabel(iso: string): string {
  const diffDays = Math.ceil((new Date(iso).getTime() - Date.now()) / 86_400_000);
  if (diffDays <= 0) return "Expired";
  if (diffDays === 1) return "Expires tomorrow";
  return `Expires in ${diffDays} days`;
}

export function MembersList({
  workspaceId,
  workspaceName,
  members,
  invites,
  isOwner,
  onChanged,
}: {
  workspaceId: number;
  workspaceName: string;
  members: MemberRow[] | null;
  invites: InviteRow[] | null;
  isOwner: boolean;
  onChanged: () => void;
}) {
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [removingId, setRemovingId] = useState<number | null>(null);

  const ownerCount = (members ?? []).filter((m) => m.role === "owner").length;
  const pending = (invites ?? []).filter((inv) => !inv.accepted_at);

  async function removeMember(member: MemberRow) {
    const displayName = member.user?.name ?? "this person";
    if (!window.confirm(`Remove ${displayName} from ${workspaceName}? They will lose access immediately.`)) return;

    setRemovingId(member.id);
    setError(null);
    try {
      await employerApi.removeMember(workspaceId, member.id);
      setMessage(`${displayName} was removed from the workspace.`);
      onChanged();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not remove that member.");
    } finally {
      setRemovingId(null);
    }
  }

  return (
    <div className="space-y-4">
      <div className="rounded-[var(--bj-dash-radius)] border bg-white p-5 sm:p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
        <div className="flex items-center gap-2.5">
          <h2 className="text-base font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Team members</h2>
          <span
            className="rounded-full px-2.5 py-0.5 text-xs font-medium"
            style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}
          >
            {members === null ? "…" : `${members.length} ${members.length === 1 ? "member" : "members"}`}
          </span>
          {!isOwner && (
            <span className="ml-auto text-xs" style={{ color: "var(--bj-dash-muted)" }}>Owners manage the team</span>
          )}
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

        {members === null ? (
          <div className="mt-4 space-y-2.5">
            {[0, 1, 2].map((i) => (
              <div key={i} className="h-[68px] animate-pulse rounded-2xl" style={{ background: "var(--bj-dash-soft)" }} />
            ))}
          </div>
        ) : members.length === 0 ? (
          <p className="mt-4 text-sm" style={{ color: "var(--bj-dash-muted)" }}>No members loaded.</p>
        ) : (
          <ul className="mt-4 space-y-2.5">
            {members.map((m) => {
              const canRemove = isOwner && !(m.role === "owner" && ownerCount <= 1);
              return (
                <li
                  key={m.id}
                  className="flex flex-wrap items-center gap-3 rounded-2xl border p-3.5 sm:flex-nowrap"
                  style={{ borderColor: "var(--bj-dash-border)" }}
                >
                  <Avatar name={m.user?.name ?? "Member"} />
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{m.user?.name ?? "Member"}</p>
                    <p className="truncate font-mono text-[11px]" style={{ color: "var(--bj-dash-muted)" }}>{m.user?.email}</p>
                  </div>
                  <span
                    className="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wide"
                    style={m.role === "owner"
                      ? { background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }
                      : { background: "#f2f4f0", color: "var(--bj-dash-muted)" }}
                  >
                    {ROLE_LABELS[m.role]}
                  </span>
                  {isOwner && (
                    <button
                      type="button"
                      onClick={() => removeMember(m)}
                      disabled={!canRemove || removingId === m.id}
                      title={canRemove ? "Remove from this workspace" : "A workspace must keep at least one owner"}
                      className="shrink-0 rounded-full border border-[#f3d5d6] px-3 py-1.5 text-xs font-semibold text-[#c0392b] transition hover:bg-[#fde8e9] disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                    >
                      {removingId === m.id ? "Removing…" : "Remove"}
                    </button>
                  )}
                </li>
              );
            })}
          </ul>
        )}
      </div>

      {/* Pending invites — sent, nobody has clicked the link yet. Kept from
          the pre-redesign page even though the approved reference doesn't
          picture it (PRD-E: don't drop an existing workflow to match a
          screenshot). */}
      {isOwner && pending.length > 0 && (
        <div className="rounded-[var(--bj-dash-radius)] border bg-white p-5 sm:p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
          <h2 className="text-base font-semibold" style={{ color: "var(--bj-dash-ink)" }}>
            Pending invites <span className="font-normal" style={{ color: "var(--bj-dash-muted)" }}>· {pending.length} waiting to join</span>
          </h2>
          <ul className="mt-4 space-y-2.5">
            {pending.map((inv) => (
              <li
                key={inv.id}
                className="flex flex-wrap items-center gap-3 rounded-2xl border border-dashed p-3.5"
                style={{ borderColor: "var(--bj-dash-border)" }}
              >
                <Avatar name={inv.name ?? inv.email} />
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{inv.name ?? inv.email}</p>
                  <p className="truncate font-mono text-[11px]" style={{ color: "var(--bj-dash-muted)" }}>
                    {inv.email}{inv.whatsapp ? ` · ${inv.whatsapp}` : ""}
                  </p>
                </div>
                <span className="whitespace-nowrap text-xs" style={{ color: "var(--bj-dash-muted)" }}>{expiryLabel(inv.expires_at)}</span>
                <span className="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" style={{ background: "#fdf1dd", color: "#a5720a" }}>
                  Pending
                </span>
                <span className="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" style={{ background: "#f2f4f0", color: "var(--bj-dash-muted)" }}>
                  {ROLE_LABELS[inv.role]}
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

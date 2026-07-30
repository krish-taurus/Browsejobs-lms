"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError } from "@/lib/api";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { employerApi, type EmployerRole, type MemberRow } from "@/lib/employer";

const ROLE_LABELS: Record<EmployerRole, string> = {
  owner: "Owner",
  recruiter: "Recruiter",
  hiring_manager: "Hiring manager",
};

const ROLE_HINTS: Record<EmployerRole, string> = {
  owner: "Manages the workspace, team and credits",
  recruiter: "Posts JDs and drives pipelines",
  hiring_manager: "Reviews shortlists and evidence",
};

export default function EmployerTeamPage() {
  const { workspace } = useWorkspace();
  const [members, setMembers] = useState<MemberRow[] | null>(null);
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<EmployerRole>("recruiter");
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const isOwner = workspace.my_role === "owner";

  const load = useCallback(() => {
    employerApi.members(workspace.id).then((res) => setMembers(res.data)).catch(() => setMembers([]));
  }, [workspace.id]);

  useEffect(load, [load]);

  async function invite(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    setMessage(null);
    try {
      await employerApi.invite(workspace.id, email, role);
      setMessage(`Invite sent to ${email}. The link is single-use and expires in 7 days.`);
      setEmail("");
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not send the invite.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div>
        <p className="mono text-[11px] uppercase tracking-widest text-trust">Team</p>
        <h1 className="display mt-1 text-2xl text-ink">{workspace.name}</h1>
      </div>

      {isOwner && (
        <form onSubmit={invite} className="rounded-panel border border-line bg-white p-6 shadow-soft">
          <p className="mono text-[10px] uppercase tracking-widest text-muted">Invite a teammate</p>
          <div className="mt-3 flex flex-wrap items-end gap-3">
            <div className="min-w-[220px] flex-1">
              <label className="mb-1 block text-sm font-medium text-ink" htmlFor="invite-email">Work email</label>
              <input
                id="invite-email"
                type="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full rounded-input border border-line px-3 py-2 text-sm"
                placeholder="colleague@company.com"
              />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-ink" htmlFor="invite-role">Role</label>
              <select
                id="invite-role"
                value={role}
                onChange={(e) => setRole(e.target.value as EmployerRole)}
                className="rounded-input border border-line px-3 py-2 text-sm"
              >
                <option value="recruiter">Recruiter</option>
                <option value="hiring_manager">Hiring manager</option>
                <option value="owner">Owner</option>
              </select>
            </div>
            <button disabled={busy} className="rounded-input bg-trust px-4 py-2 text-sm font-semibold text-white shadow-soft disabled:opacity-50">
              {busy ? "Sending…" : "Send invite"}
            </button>
          </div>
          <p className="mt-2 text-xs text-muted">{ROLE_HINTS[role]}</p>
          {message && <p className="mt-2 text-sm text-verify">{message}</p>}
          {error && <p className="mt-2 text-sm text-warn">{error}</p>}
        </form>
      )}

      <div className="rounded-panel border border-line bg-white p-6 shadow-soft">
        <p className="mono text-[10px] uppercase tracking-widest text-muted">Members</p>
        {members === null ? (
          <div className="mt-3 space-y-2">{[0, 1].map((i) => <div key={i} className="shimmer h-12 rounded-lg" />)}</div>
        ) : (
          <ul className="mt-3 divide-y divide-line">
            {members.map((m) => (
              <li key={m.id} className="flex items-center justify-between py-3">
                <div>
                  <p className="text-sm font-medium text-ink">{m.user?.name ?? "Member"}</p>
                  <p className="text-xs text-muted">{m.user?.email}</p>
                </div>
                <span className="mono rounded-full bg-sky px-3 py-1 text-[10px] uppercase tracking-widest text-deep">
                  {ROLE_LABELS[m.role]}
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>

      {!isOwner && (
        <p className="text-xs text-muted">Only workspace owners can invite or change teammates.</p>
      )}
    </div>
  );
}

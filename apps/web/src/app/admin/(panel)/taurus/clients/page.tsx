"use client";

import { useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type Workspace = {
  id: number;
  name: string;
  slug: string;
  status: "active" | "suspended";
  is_owner: boolean;
  members_count: number;
  agents_count: number;
  last_activity_at: string | null;
  created_at: string;
};

const inputCls = "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";
const errText = (e: unknown, fallback: string) => (e instanceof ApiError ? (e.firstError ?? e.message) : fallback);
const when = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString("en-IN", { timeZone: "Asia/Kolkata", day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" }) : "—";

/**
 * Taurus → Clients (owner only). Each client business gets its own workspace:
 * its own agents, keys and agent token. This page shows counts only — never a
 * client's agents or keys.
 */
export default function TaurusClientsPage() {
  const qc = useQueryClient();
  const { data, isLoading, error } = useQuery({
    queryKey: ["admin", "taurus", "workspaces"],
    queryFn: () => apiJson<{ data: Workspace[] }>("/api/v1/admin/taurus/workspaces").then((r) => r.data),
  });
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [ownerName, setOwnerName] = useState("");
  const [invite, setInvite] = useState<{ who: string; url: string } | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const create = useMutation({
    mutationFn: () =>
      apiJson<{ data: { workspace: Workspace; invite_url: string } }>("/api/v1/admin/taurus/workspaces", {
        method: "POST",
        body: JSON.stringify({ name, owner_email: email, owner_name: ownerName || undefined }),
      }),
    onSuccess: (r) => {
      setInvite({ who: email, url: r.data.invite_url });
      setName("");
      setEmail("");
      setOwnerName("");
      setNotice(null);
      void qc.invalidateQueries({ queryKey: ["admin", "taurus", "workspaces"] });
    },
    onError: (e) => setNotice(errText(e, "Couldn't create the workspace.")),
  });
  const setStatus = useMutation({
    mutationFn: (v: { id: number; status: Workspace["status"] }) =>
      apiJson(`/api/v1/admin/taurus/workspaces/${v.id}`, { method: "PATCH", body: JSON.stringify({ status: v.status }) }),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["admin", "taurus", "workspaces"] }),
    onError: (e) => setNotice(errText(e, "Couldn't change the status.")),
  });
  const reinvite = useMutation({
    mutationFn: (v: { id: number; email: string }) =>
      apiJson<{ data: { invite_url: string } }>(`/api/v1/admin/taurus/workspaces/${v.id}/invites`, {
        method: "POST",
        body: JSON.stringify({ email: v.email, role: "owner" }),
      }).then((r) => ({ who: v.email, url: r.data.invite_url })),
    onSuccess: setInvite,
    onError: (e) => setNotice(errText(e, "Couldn't create the invite.")),
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    create.mutate();
  };

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Taurus AI</p>
      <h1 className="display mt-2 text-3xl text-ink">Clients</h1>
      <p className="mt-1 text-sm text-muted">
        Give a business its own Taurus workspace. They connect their own agents and keys, and see only their own floor. You see counts here,
        never their agents or keys.
      </p>

      {notice && (
        <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn" role="alert">
          {notice}
        </p>
      )}
      {invite && (
        <div className="mt-4 rounded-[14px] border border-verify/40 bg-verify-bg p-4">
          <p className="text-sm font-semibold text-ink">Invite link for {invite.who}</p>
          <p className="mt-1 text-xs text-muted">Send this to them. It works once and expires. It won&apos;t be shown again.</p>
          <div className="mt-2 flex gap-2">
            <input readOnly value={invite.url} className={`${inputCls} mono`} onFocus={(e) => e.currentTarget.select()} aria-label="Invite link" />
            <button
              type="button"
              className="rounded-full bg-ink px-4 text-sm font-semibold text-white"
              onClick={() =>
                navigator.clipboard?.writeText(invite.url).then(
                  () => setNotice(null),
                  () => setNotice("Copy blocked by the browser. Select the link and copy it."),
                )
              }
            >
              Copy
            </button>
          </div>
        </div>
      )}

      <form onSubmit={submit} className="mt-6 grid gap-3 rounded-[14px] border border-line bg-white p-5 sm:grid-cols-3 sm:items-end">
        <h2 className="display text-lg text-ink sm:col-span-3">New client workspace</h2>
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-ink">Business name</span>
          <input id="client-name" required value={name} onChange={(e) => setName(e.target.value)} placeholder="Acme Logistics" className={inputCls} />
        </label>
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-ink">Owner email</span>
          <input id="client-email" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} placeholder="founder@acme.com" className={inputCls} />
        </label>
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-ink">Owner name (optional)</span>
          <input id="client-owner" value={ownerName} onChange={(e) => setOwnerName(e.target.value)} className={inputCls} />
        </label>
        <div className="sm:col-span-3">
          <button type="submit" disabled={create.isPending} className="rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
            {create.isPending ? "Creating…" : "Create workspace and invite"}
          </button>
        </div>
      </form>

      <section className="mt-8">
        <h2 className="display text-xl text-ink">Workspaces</h2>
        {isLoading ? (
          <div className="shimmer mt-3 h-32 rounded-[14px]" />
        ) : error ? (
          <p className="mt-3 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">
            {error instanceof ApiError && error.status === 403 ? "Only the platform owner can manage Taurus clients." : "Couldn't load workspaces."}
          </p>
        ) : !data?.length ? (
          <p className="mt-3 text-sm text-muted">No workspaces yet. Create your first client above.</p>
        ) : (
          <div className="mt-3 overflow-x-auto rounded-[14px] border border-line bg-white">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-line text-xs uppercase tracking-wider text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">Workspace</th>
                  <th className="px-4 py-3 font-medium">Members</th>
                  <th className="px-4 py-3 font-medium">Agents</th>
                  <th className="px-4 py-3 font-medium">Last activity (IST)</th>
                  <th className="px-4 py-3 font-medium">Status</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {data.map((w) => (
                  <tr key={w.id}>
                    <td className="px-4 py-3">
                      <span className="font-semibold text-ink">{w.name}</span>
                      {w.is_owner && <span className="mono ml-2 rounded-full bg-ink px-2 py-0.5 text-[10px] text-white">YOURS</span>}
                    </td>
                    <td className="mono px-4 py-3 text-ink2">{w.members_count}</td>
                    <td className="mono px-4 py-3 text-ink2">{w.agents_count}</td>
                    <td className="mono px-4 py-3 text-ink2">{when(w.last_activity_at)}</td>
                    <td className="px-4 py-3">
                      <span className={`mono rounded-full px-2 py-0.5 text-[11px] ${w.status === "active" ? "bg-verify-bg text-verify" : "bg-warn/10 text-warn"}`}>{w.status}</span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      {!w.is_owner && (
                        <div className="flex justify-end gap-2">
                          <InviteButton busy={reinvite.isPending} onInvite={(email) => reinvite.mutate({ id: w.id, email })} />
                          <button
                            type="button"
                            disabled={setStatus.isPending}
                            onClick={() => setStatus.mutate({ id: w.id, status: w.status === "active" ? "suspended" : "active" })}
                            className="rounded-full border border-line px-3 py-1 text-xs font-semibold text-ink hover:border-trust disabled:opacity-50"
                          >
                            {w.status === "active" ? "Pause" : "Resume"}
                          </button>
                        </div>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

function InviteButton({ busy, onInvite }: { busy: boolean; onInvite: (email: string) => void }) {
  const [open, setOpen] = useState(false);
  const [email, setEmail] = useState("");
  if (!open)
    return (
      <button type="button" onClick={() => setOpen(true)} className="rounded-full border border-line px-3 py-1 text-xs font-semibold text-ink hover:border-trust">
        Invite
      </button>
    );
  return (
    <form
      className="flex gap-1"
      onSubmit={(e) => {
        e.preventDefault();
        onInvite(email);
        setOpen(false);
        setEmail("");
      }}
    >
      <input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} placeholder="email" aria-label="Invite email" className="w-40 rounded-full border border-line px-3 py-1 text-xs" />
      <button type="submit" disabled={busy} className="rounded-full bg-ink px-3 py-1 text-xs font-semibold text-white">
        Send
      </button>
    </form>
  );
}

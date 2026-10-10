"use client";

import { useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import "@/components/ap/pages/taurus-ui.css";
import { taurusDisplayFont } from "@/components/ap/pages/taurus-font";
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
    <div className={`tx-ap tx-ap--card ${taurusDisplayFont.variable}`}>
      <div className="tx-narrow" style={{ maxWidth: 880 }}>
        <p className="tx-eyebrow">Taurus AI</p>
        <h1 className="tx-title">Clients</h1>
        <p className="tx-sub">
          Give a business its own Taurus workspace. They connect their own agents and keys, and see only their own floor. You see counts here,
          never their agents or keys.
        </p>

        {notice && (
          <p className="tx-note tx-note--error" role="alert">
            {notice}
          </p>
        )}
        {invite && (
          <div className="tx-reveal" style={{ marginTop: 16 }}>
            <p>
              Invite link for {invite.who}
              <span className="tx-row-sub" style={{ display: "block" }}>
                Send this to them. It works once and expires. It won&apos;t be shown again.
              </span>
            </p>
            <div className="tx-copy">
              <input readOnly value={invite.url} className="tx-input tx-mono" onFocus={(e) => e.currentTarget.select()} aria-label="Invite link" />
              <button
                type="button"
                className="tx-btn tx-btn-primary"
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

        <section className="tx-section">
          <div className="tx-section-head">
            <h2 className="tx-h2">New client workspace</h2>
            <p className="tx-section-note">Creates the workspace and an invite link for its owner.</p>
          </div>
          <form onSubmit={submit} className="tx-group">
            <div className="tx-row tx-row--block">
              <div className="tx-fields tx-fields--3">
                <div>
                  <label className="tx-label" htmlFor="client-name">
                    Business name
                  </label>
                  <input id="client-name" required value={name} onChange={(e) => setName(e.target.value)} placeholder="Acme Logistics" className="tx-input" />
                </div>
                <div>
                  <label className="tx-label" htmlFor="client-email">
                    Owner email
                  </label>
                  <input id="client-email" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} placeholder="founder@acme.com" className="tx-input" />
                </div>
                <div>
                  <label className="tx-label" htmlFor="client-owner">
                    Owner name (optional)
                  </label>
                  <input id="client-owner" value={ownerName} onChange={(e) => setOwnerName(e.target.value)} className="tx-input" />
                </div>
              </div>
              <div className="tx-actions" style={{ marginTop: 16 }}>
                <button type="submit" disabled={create.isPending} className="tx-btn tx-btn-primary">
                  {create.isPending ? "Creating…" : "Create workspace and invite"}
                </button>
              </div>
            </div>
          </form>
        </section>

        <section className="tx-section">
          <div className="tx-section-head">
            <h2 className="tx-h2">Workspaces</h2>
            {!!data?.length && <p className="tx-section-note">Last activity in IST.</p>}
          </div>
          {isLoading ? (
            <div className="shimmer tx-skel" style={{ height: 128 }} />
          ) : error ? (
            <p className="tx-note tx-note--warn">
              {error instanceof ApiError && error.status === 403 ? "Only the platform owner can manage Taurus clients." : "Couldn't load workspaces."}
            </p>
          ) : !data?.length ? (
            <p className="tx-section-note" style={{ marginInline: 4 }}>
              No workspaces yet. Create your first client above.
            </p>
          ) : (
            <ul className="tx-group" style={{ margin: 0, padding: 0, listStyle: "none" }}>
              {data.map((w) => (
                <li key={w.id} className="tx-row tx-ws-row">
                  <div className="tx-row-main">
                    <p className="tx-row-label">
                      {w.name}
                      {w.is_owner && <span className="tx-badge tx-badge--dark">Yours</span>}
                      <span className={`tx-badge ${w.status === "active" ? "tx-badge--ok" : "tx-badge--warn"}`}>{w.status === "active" ? "Active" : "Paused"}</span>
                    </p>
                    <p className="tx-ws-meta tx-num">
                      <span>{w.members_count} members</span>
                      <span>{w.agents_count} agents</span>
                      <span>Last activity {when(w.last_activity_at)}</span>
                    </p>
                  </div>
                  {!w.is_owner && (
                    <div className="tx-actions">
                      <InviteButton busy={reinvite.isPending} onInvite={(email) => reinvite.mutate({ id: w.id, email })} />
                      <button
                        type="button"
                        disabled={setStatus.isPending}
                        onClick={() => setStatus.mutate({ id: w.id, status: w.status === "active" ? "suspended" : "active" })}
                        className="tx-btn tx-btn-outline tx-btn-sm"
                      >
                        {w.status === "active" ? "Pause" : "Resume"}
                      </button>
                    </div>
                  )}
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  );
}

function InviteButton({ busy, onInvite }: { busy: boolean; onInvite: (email: string) => void }) {
  const [open, setOpen] = useState(false);
  const [email, setEmail] = useState("");
  if (!open)
    return (
      <button type="button" onClick={() => setOpen(true)} className="tx-btn tx-btn-secondary tx-btn-sm">
        Invite
      </button>
    );
  return (
    <form
      className="tx-invite-inline"
      onSubmit={(e) => {
        e.preventDefault();
        onInvite(email);
        setOpen(false);
        setEmail("");
      }}
    >
      <input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} placeholder="email" aria-label="Invite email" className="tx-input" />
      <button type="submit" disabled={busy} className="tx-btn tx-btn-primary tx-btn-sm">
        Send
      </button>
    </form>
  );
}

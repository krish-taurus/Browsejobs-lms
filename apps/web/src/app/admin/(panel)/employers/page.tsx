"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

/**
 * Admin-side employer onboarding (PRD-E F1).
 *
 * The sales-led path: a customer signs, ops creates their workspace here and
 * sends the owner a link. Until now this needed an engineer with SSH access.
 *
 * Two things the screen is deliberate about:
 *
 *  - There is no password field. BrowseJobs never chooses a customer's
 *    credential; the owner sets their own from the invite link.
 *  - The claim link is shown once, at creation, and never again. It is a
 *    live credential-setting link, and a listing screen is seen by more
 *    people than should be able to use one.
 */

type Employer = {
  id: number;
  name: string;
  slug: string;
  industry: string | null;
  company_size: string | null;
  status: "active" | "suspended";
  members_count: number;
  jobs_count: number;
  owner: { id: number; name: string; email: string } | null;
  pending_invite: { email: string; expires_at: string | null } | null;
  created_at: string | null;
};

type Onboarded = {
  workspace: { id: number; name: string; slug: string };
  owner: { id: number; name: string; email: string };
  invite: { claim_url: string; expires_at: string | null };
};

const inputCls =
  "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function AdminEmployersPage() {
  const qc = useQueryClient();
  const [form, setForm] = useState({ company: "", owner_name: "", owner_email: "", industry: "" });
  const [created, setCreated] = useState<Onboarded | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "employers"],
    queryFn: () => apiJson<{ data: Employer[] }>("/api/v1/admin/employers"),
  });

  const onboard = useMutation({
    mutationFn: () =>
      apiJson<{ data: Onboarded }>("/api/v1/admin/employers", {
        method: "POST",
        body: JSON.stringify({
          company: form.company,
          owner_email: form.owner_email,
          owner_name: form.owner_name || null,
          industry: form.industry || null,
        }),
      }),
    onSuccess: (res) => {
      setCreated(res.data);
      setCopied(false);
      setError(null);
      setForm({ company: "", owner_name: "", owner_email: "", industry: "" });
      void qc.invalidateQueries({ queryKey: ["admin", "employers"] });
    },
    onError: (e: unknown) =>
      setError(e instanceof ApiError ? (e.firstError ?? e.message) : "Could not onboard that employer."),
  });

  const setStatus = useMutation({
    mutationFn: (v: { id: number; status: "active" | "suspended" }) =>
      apiJson(`/api/v1/admin/employers/${v.id}/status`, {
        method: "POST",
        body: JSON.stringify({ status: v.status }),
      }),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["admin", "employers"] }),
  });

  const rows = data?.data ?? [];

  return (
    <div className="space-y-6">
      <header>
        <h1 className="display text-2xl text-ink">Employers</h1>
        <p className="mt-1 text-sm text-muted">
          Create a hiring workspace and send its owner a link to set their own password. Nobody here
          ever chooses a customer&apos;s password.
        </p>
      </header>

      {/* Onboard ------------------------------------------------------- */}
      <section className="rounded-[14px] border border-line bg-white p-5">
        <h2 className="display text-lg text-ink">Onboard an employer</h2>

        <div className="mt-4 grid gap-4 md:grid-cols-2">
          <label className="block">
            <span className="text-xs font-semibold text-ink">Company</span>
            <input
              className={`${inputCls} mt-1`}
              value={form.company}
              onChange={(e) => setForm({ ...form, company: e.target.value })}
              placeholder="Acme Technologies"
            />
          </label>
          <label className="block">
            <span className="text-xs font-semibold text-ink">Owner email</span>
            <input
              type="email"
              className={`${inputCls} mt-1`}
              value={form.owner_email}
              onChange={(e) => setForm({ ...form, owner_email: e.target.value })}
              placeholder="hiring@acme.com"
            />
          </label>
          <label className="block">
            <span className="text-xs font-semibold text-ink">Owner name</span>
            <input
              className={`${inputCls} mt-1`}
              value={form.owner_name}
              onChange={(e) => setForm({ ...form, owner_name: e.target.value })}
              placeholder="Priya Rao"
            />
          </label>
          <label className="block">
            <span className="text-xs font-semibold text-ink">Industry</span>
            <input
              className={`${inputCls} mt-1`}
              value={form.industry}
              onChange={(e) => setForm({ ...form, industry: e.target.value })}
              placeholder="Retail"
            />
          </label>
        </div>

        <button
          onClick={() => onboard.mutate()}
          disabled={onboard.isPending || form.company.trim() === "" || form.owner_email.trim() === ""}
          className="mt-4 rounded-[10px] bg-trust px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
        >
          {onboard.isPending ? "Creating…" : "Create workspace & invite"}
        </button>

        {error && <p className="mt-3 text-sm text-warn">{error}</p>}

        {created && (
          <div className="mt-5 rounded-[10px] border border-verify/30 bg-green-bg p-4">
            <p className="text-sm font-semibold text-ink">
              {created.workspace.name} is ready. Send this link to {created.owner.email}.
            </p>
            <p className="mt-1 text-xs text-muted">
              It is shown once and never again — if it is lost, onboard again to issue a fresh one.
              It expires {created.invite.expires_at?.slice(0, 10) ?? "soon"}.
            </p>
            <div className="mt-3 flex flex-wrap items-center gap-2">
              <code className="mono flex-1 break-all rounded-[10px] border border-line bg-white px-3 py-2 text-xs text-ink">
                {created.invite.claim_url}
              </code>
              <button
                onClick={() => {
                  void navigator.clipboard.writeText(created.invite.claim_url);
                  setCopied(true);
                }}
                className="rounded-[10px] border border-line px-3 py-2 text-xs font-semibold text-ink"
              >
                {copied ? "Copied" : "Copy"}
              </button>
            </div>
          </div>
        )}
      </section>

      {/* Existing ------------------------------------------------------ */}
      <section className="rounded-[14px] border border-line bg-white p-5">
        <h2 className="display text-lg text-ink">On the platform</h2>

        {isLoading ? (
          <p className="mt-3 text-sm text-muted">Loading…</p>
        ) : rows.length === 0 ? (
          <p className="mt-3 text-sm text-muted">No employers yet.</p>
        ) : (
          <div className="mt-4 overflow-x-auto">
            <table className="w-full min-w-[720px] text-left text-sm">
              <thead>
                <tr className="border-b border-line text-xs uppercase tracking-wider text-muted">
                  <th className="pb-2">Company</th>
                  <th className="pb-2">Owner</th>
                  <th className="pb-2">JDs</th>
                  <th className="pb-2">Team</th>
                  <th className="pb-2">Status</th>
                  <th className="pb-2" />
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-line/60">
                    <td className="py-3">
                      <span className="font-semibold text-ink">{row.name}</span>
                      {row.industry && <span className="ml-2 text-xs text-muted">{row.industry}</span>}
                    </td>
                    <td className="py-3 text-ink-2">
                      {row.owner?.email ?? "—"}
                      {/* The distinction between "invited" and "using it" is
                          the difference between chasing a customer and
                          leaving them alone. */}
                      {row.pending_invite && (
                        <span className="ml-2 rounded-full bg-sky px-2 py-0.5 text-[10px] font-semibold text-deep">
                          invite not used
                        </span>
                      )}
                    </td>
                    <td className="mono py-3 text-ink-2">{row.jobs_count}</td>
                    <td className="mono py-3 text-ink-2">{row.members_count}</td>
                    <td className="py-3">
                      <span
                        className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                          row.status === "active"
                            ? "bg-green-bg text-verify"
                            : "bg-red-50 text-warn"
                        }`}
                      >
                        {row.status}
                      </span>
                    </td>
                    <td className="py-3 text-right">
                      <button
                        onClick={() =>
                          setStatus.mutate({
                            id: row.id,
                            status: row.status === "active" ? "suspended" : "active",
                          })
                        }
                        disabled={setStatus.isPending}
                        className="rounded-[10px] border border-line px-3 py-1.5 text-xs font-semibold text-ink disabled:opacity-50"
                      >
                        {row.status === "active" ? "Suspend" : "Restore"}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        <p className="mt-4 text-xs text-muted">
          Suspending cuts the whole team out of the portal immediately — nobody on it can see
          candidates or move anyone through a pipeline until it is restored. Both actions are audited.
        </p>
      </section>
    </div>
  );
}

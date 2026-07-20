"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

/**
 * Team management (admins with manage-users): create staff — trainers,
 * mentors, counselors — assign their roles, reset passwords and
 * activate/deactivate. The API enforces the same permission and tenant scope.
 */

type StaffRole = { slug: string; name: string };
type Staff = {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  is_active: boolean;
  roles: string[];
};
type TeamResponse = { data: Staff[]; roles: StaffRole[] };

const inputCls =
  "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

const EMPTY = { name: "", email: "", phone: "", password: "", roles: [] as string[] };

export default function AdminTeamPage() {
  const qc = useQueryClient();
  const [form, setForm] = useState(EMPTY);
  const [showPw, setShowPw] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "team"],
    queryFn: () => apiJson<TeamResponse>("/api/v1/admin/team"),
  });

  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "team"] });
  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const create = useMutation({
    mutationFn: () =>
      apiJson("/api/v1/admin/team", {
        method: "POST",
        body: JSON.stringify({
          name: form.name.trim(),
          email: form.email.trim(),
          phone: form.phone.trim() || null,
          password: form.password,
          roles: form.roles,
        }),
      }),
    onSuccess: () => {
      setForm(EMPTY);
      setError(null);
      void refresh();
    },
    onError,
  });

  const setActive = useMutation({
    mutationFn: (v: { id: number; active: boolean }) =>
      apiJson(`/api/v1/admin/team/${v.id}/active`, {
        method: "POST",
        body: JSON.stringify({ active: v.active }),
      }),
    onSuccess: () => void refresh(),
    onError,
  });

  const setRoles = useMutation({
    mutationFn: (v: { id: number; roles: string[] }) =>
      apiJson(`/api/v1/admin/team/${v.id}`, {
        method: "PATCH",
        body: JSON.stringify({ roles: v.roles }),
      }),
    onSuccess: () => void refresh(),
    onError,
  });

  const allRoles = data?.roles ?? [];
  const toggleFormRole = (slug: string) =>
    setForm((f) => ({
      ...f,
      roles: f.roles.includes(slug) ? f.roles.filter((r) => r !== slug) : [...f.roles, slug],
    }));

  const canCreate =
    form.name.trim() && form.email.trim() && form.password.length >= 10 && form.roles.length > 0;

  return (
    <div className="space-y-8">
      <div>
        <h1 className="display text-2xl text-ink">Team</h1>
        <p className="mt-1 text-sm text-ink2/70">
          Create staff accounts and assign roles. They sign in at the staff
          portal; each role unlocks only its part of the panel.
        </p>
      </div>

      {/* Create form */}
      <form
        onSubmit={(e) => {
          e.preventDefault();
          create.mutate();
        }}
        className="grid gap-3 rounded-[14px] border border-line bg-white p-5 shadow-soft md:grid-cols-2"
      >
        <input className={inputCls} placeholder="Full name" value={form.name}
          onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} required />
        <input className={inputCls} type="email" placeholder="Work email" value={form.email}
          onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} required />
        <input className={inputCls} placeholder="Phone (optional)" value={form.phone}
          onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} />
        <div className="relative">
          <input className={`${inputCls} pr-16`} type={showPw ? "text" : "password"}
            placeholder="Temporary password (min 10)" value={form.password}
            onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))} required />
          <button type="button" onClick={() => setShowPw((v) => !v)}
            className="absolute inset-y-0 right-3 my-auto h-fit text-xs font-semibold text-muted hover:text-trust">
            {showPw ? "Hide" : "Show"}
          </button>
        </div>

        <div className="md:col-span-2">
          <p className="mono mb-2 text-[10px] uppercase tracking-[0.16em] text-muted">Roles</p>
          <div className="flex flex-wrap gap-2">
            {allRoles.map((r) => (
              <button key={r.slug} type="button" onClick={() => toggleFormRole(r.slug)}
                className={`rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors ${
                  form.roles.includes(r.slug)
                    ? "bg-trust text-white"
                    : "border border-line text-ink2 hover:border-trust"
                }`}>
                {r.name}
              </button>
            ))}
          </div>
        </div>

        <div className="flex items-center justify-between gap-3 md:col-span-2">
          {error ? <p className="text-sm text-warn">{error}</p> : <span />}
          <button type="submit" disabled={!canCreate || create.isPending}
            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50">
            {create.isPending ? "Creating…" : "Create staff"}
          </button>
        </div>
      </form>

      {/* Staff list */}
      {isLoading ? (
        <div className="shimmer h-24 rounded-[14px]" />
      ) : (
        <div className="space-y-3">
          {(data?.data ?? []).map((s) => (
            <div key={s.id}
              className={`rounded-[14px] border bg-white p-4 shadow-soft ${s.is_active ? "border-line" : "border-line opacity-60"}`}>
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <span className="font-semibold text-ink">{s.name}</span>
                  <span className="ml-2 text-sm text-muted">{s.email}</span>
                  {!s.is_active && (
                    <span className="mono ml-2 rounded-full bg-warn/10 px-2 py-0.5 text-[10px] uppercase text-warn">
                      deactivated
                    </span>
                  )}
                </div>
                <button
                  onClick={() => setActive.mutate({ id: s.id, active: !s.is_active })}
                  className={`rounded-full border px-3.5 py-1.5 text-xs font-semibold transition-colors ${
                    s.is_active
                      ? "border-warn/40 text-warn hover:bg-warn/5"
                      : "border-verify/40 text-verify hover:bg-verify/5"
                  }`}>
                  {s.is_active ? "Deactivate" : "Reactivate"}
                </button>
              </div>
              <div className="mt-3 flex flex-wrap gap-2">
                {allRoles.map((r) => {
                  const on = s.roles.includes(r.slug);
                  return (
                    <button key={r.slug}
                      onClick={() => {
                        const next = on ? s.roles.filter((x) => x !== r.slug) : [...s.roles, r.slug];
                        if (next.length === 0) return; // must keep at least one role
                        setRoles.mutate({ id: s.id, roles: next });
                      }}
                      className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                        on ? "bg-ink text-white" : "border border-line text-muted hover:border-trust"
                      }`}>
                      {r.name}
                    </button>
                  );
                })}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

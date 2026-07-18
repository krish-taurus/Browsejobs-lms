"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type Tenant = {
  id: number;
  name: string;
  slug: string;
  domain: string | null;
  plan: string;
  status: string;
  users_count: number;
  branding: { colors?: Record<string, string>; fonts?: Record<string, string>; logo_url?: string | null } | null;
  feature_flags: Record<string, boolean>;
};

const COLOR_KEYS = ["trust_blue", "deep_navy", "ink_navy", "verify_green", "amber", "warn_red"];
const FLAG_KEYS = ["crm", "ai_coach", "mock_interviewer", "placement", "leaderboards"];

export default function TenantsPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [newTenant, setNewTenant] = useState({ name: "", slug: "", domain: "" });
  const [editing, setEditing] = useState<number | null>(null);

  const { data } = useQuery({
    queryKey: ["admin", "tenants"],
    queryFn: () => apiJson<{ data: Tenant[] }>("/api/v1/admin/tenants"),
  });
  const tenants = data?.data ?? [];
  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "tenants"] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");

  const provision = useMutation({
    mutationFn: () =>
      apiJson("/api/v1/admin/tenants", {
        method: "POST",
        body: JSON.stringify({ name: newTenant.name, slug: newTenant.slug || undefined, domain: newTenant.domain || null }),
      }),
    onSuccess: () => { setNewTenant({ name: "", slug: "", domain: "" }); setError(null); refresh(); },
    onError,
  });

  const saveColor = useMutation({
    mutationFn: ({ id, key, value }: { id: number; key: string; value: string }) =>
      apiJson(`/api/v1/admin/tenants/${id}/branding`, {
        method: "PUT",
        body: JSON.stringify({ branding: { colors: { [key]: value } } }),
      }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });

  const saveFlag = useMutation({
    mutationFn: ({ id, key, value }: { id: number; key: string; value: boolean }) =>
      apiJson(`/api/v1/admin/tenants/${id}/flags`, {
        method: "PUT",
        body: JSON.stringify({ feature_flags: { [key]: value } }),
      }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Whitelabel</p>
      <h1 className="display mt-2 text-3xl text-ink">Tenants</h1>
      <p className="mt-1 text-sm text-muted">Provision institutes, theme their whitelabel branding, and toggle features. Super-admin only.</p>

      {error && <p className="mt-4 text-sm text-warn">{error}</p>}

      {/* Provision */}
      <div className="mt-6 rounded-[14px] border border-line bg-white p-5">
        <h2 className="text-sm font-semibold text-ink">Provision a tenant</h2>
        <div className="mt-3 grid gap-3 sm:grid-cols-3">
          <input value={newTenant.name} onChange={(e) => setNewTenant({ ...newTenant, name: e.target.value })} placeholder="Institute name" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
          <input value={newTenant.slug} onChange={(e) => setNewTenant({ ...newTenant, slug: e.target.value })} placeholder="slug (optional)" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
          <input value={newTenant.domain} onChange={(e) => setNewTenant({ ...newTenant, domain: e.target.value })} placeholder="domain (optional)" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
        </div>
        <button onClick={() => provision.mutate()} disabled={!newTenant.name.trim() || provision.isPending} className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {provision.isPending ? "Creating…" : "Provision"}
        </button>
      </div>

      {/* Tenants */}
      <div className="mt-6 space-y-3">
        {tenants.map((t) => (
          <div key={t.id} className="rounded-[14px] border border-line bg-white p-5">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p className="text-sm font-semibold text-ink">{t.name} <span className="mono text-[11px] text-muted">/{t.slug}</span></p>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">
                  {[t.domain ?? "no domain", t.plan, `${t.users_count} users`, t.status].join(" · ")}
                </p>
              </div>
              <button onClick={() => setEditing(editing === t.id ? null : t.id)} className="text-xs font-semibold text-trust hover:underline">
                {editing === t.id ? "Close" : "Theme & flags"}
              </button>
            </div>

            {editing === t.id && (
              <div className="mt-4 space-y-4 border-t border-line pt-4">
                <div>
                  <p className="mono pb-2 text-[10px] uppercase tracking-widest text-muted">Brand colours</p>
                  <div className="flex flex-wrap gap-3">
                    {COLOR_KEYS.map((key) => (
                      <label key={key} className="flex items-center gap-2 text-xs text-ink">
                        <input
                          type="color"
                          value={t.branding?.colors?.[key] ?? "#1B6DF0"}
                          onChange={(e) => saveColor.mutate({ id: t.id, key, value: e.target.value })}
                          className="h-7 w-9 cursor-pointer rounded border border-line"
                        />
                        {key.replace(/_/g, " ")}
                      </label>
                    ))}
                  </div>
                </div>
                <div>
                  <p className="mono pb-2 text-[10px] uppercase tracking-widest text-muted">Features</p>
                  <div className="flex flex-wrap gap-3">
                    {FLAG_KEYS.map((key) => {
                      const on = t.feature_flags[key] ?? true;
                      return (
                        <button
                          key={key}
                          onClick={() => saveFlag.mutate({ id: t.id, key, value: !on })}
                          className={`mono rounded-full px-3 py-1 text-[11px] uppercase tracking-widest ${on ? "bg-verify-bg text-verify" : "bg-paper text-muted"}`}
                        >
                          {on ? "✓" : "○"} {key.replace(/_/g, " ")}
                        </button>
                      );
                    })}
                  </div>
                </div>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

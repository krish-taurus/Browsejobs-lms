"use client";

import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { formatPaise } from "@/lib/money";

type Settings = {
  cv_free_grants: number;
  voice_included_live: number;
  voice_included_self_paced: number;
  self_paced_pct_bps: number;
  text_practice_enabled: boolean;
};

type Product = {
  id: number;
  sku: string;
  name: string;
  feature: string | null;
  kind: string;
  price_paise: number;
  grant_amount: number;
  active: boolean;
};

const inputCls = "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

function describeKind(p: Product): string {
  if (p.kind === "subscription") return `${formatPaise(p.price_paise)} / mo`;
  if (p.kind === "self_paced") return `${formatPaise(p.price_paise)} · recorded`;
  return `${formatPaise(p.price_paise)}${p.grant_amount ? ` · +${p.grant_amount}` : ""}`;
}

export default function MonetizationPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [settings, setSettings] = useState<Settings | null>(null);
  const [form, setForm] = useState({ sku: "", name: "", feature: "cv", kind: "pack", rupees: "", grant_amount: "" });

  const settingsQuery = useQuery({ queryKey: ["admin", "monetization-settings"], queryFn: () => apiJson<{ data: Settings }>("/api/v1/admin/monetization-settings") });
  const products = useQuery({ queryKey: ["admin", "products"], queryFn: () => apiJson<{ data: Product[] }>("/api/v1/admin/products") });

  useEffect(() => { if (settingsQuery.data) setSettings(settingsQuery.data.data); }, [settingsQuery.data]);

  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const saveSettings = useMutation({
    mutationFn: (body: Partial<Settings>) => apiJson("/api/v1/admin/monetization-settings", { method: "PUT", body: JSON.stringify(body) }),
    onSuccess: () => { setError(null); void qc.invalidateQueries({ queryKey: ["admin", "monetization-settings"] }); },
    onError,
  });

  const createProduct = useMutation({
    mutationFn: () => apiJson("/api/v1/admin/products", {
      method: "POST",
      body: JSON.stringify({
        sku: form.sku, name: form.name, feature: form.feature, kind: form.kind,
        price_paise: Math.round(Number(form.rupees) * 100), grant_amount: Number(form.grant_amount) || 0,
      }),
    }),
    onSuccess: () => { setForm({ sku: "", name: "", feature: "cv", kind: "pack", rupees: "", grant_amount: "" }); void qc.invalidateQueries({ queryKey: ["admin", "products"] }); },
    onError,
  });

  const deactivate = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/products/${id}`, { method: "DELETE" }),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["admin", "products"] }),
    onError,
  });

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Revenue engine</p>
      <h1 className="display mt-2 text-3xl text-ink">Monetization</h1>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error} <button onClick={() => setError(null)} className="mono underline">dismiss</button></p>}

      {/* Settings */}
      <h2 className="display mt-8 text-xl text-ink">Included quotas &amp; toggles</h2>
      {settings && (
        <div className="mt-4 grid gap-3 rounded-[14px] border border-line bg-white p-5 sm:grid-cols-2">
          {([
            ["cv_free_grants", "Free CV generations"],
            ["voice_included_live", "Voice mocks · live"],
            ["voice_included_self_paced", "Voice mocks · self-paced"],
          ] as const).map(([key, label]) => (
            <label key={key} className="flex items-center justify-between gap-2 text-sm text-ink">
              {label}
              <input type="number" min={0} defaultValue={settings[key]} className={`${inputCls} w-24`}
                onBlur={(e) => { const v = Number(e.target.value); if (v !== settings[key]) saveSettings.mutate({ [key]: v }); }} />
            </label>
          ))}
          <label className="flex items-center justify-between gap-2 text-sm text-ink">
            Self-paced price (% of live)
            <input type="number" min={0} max={100} defaultValue={settings.self_paced_pct_bps / 100} className={`${inputCls} w-24`}
              onBlur={(e) => { const v = Math.round(Number(e.target.value) * 100); if (v !== settings.self_paced_pct_bps) saveSettings.mutate({ self_paced_pct_bps: v }); }} />
          </label>
          <label className="flex items-center gap-2 text-sm text-ink sm:col-span-2">
            <input type="checkbox" defaultChecked={settings.text_practice_enabled} className="h-4 w-4 rounded border-line text-trust"
              onChange={(e) => saveSettings.mutate({ text_practice_enabled: e.target.checked })} />
            Free unmetered text-practice mode
          </label>
        </div>
      )}

      {/* Products */}
      <h2 className="display mt-10 text-xl text-ink">Product catalog</h2>
      <form onSubmit={(e) => { e.preventDefault(); createProduct.mutate(); }} className="mt-3 grid gap-2.5 rounded-[14px] border border-line bg-white p-5 sm:grid-cols-2">
        <input required value={form.sku} onChange={(e) => setForm({ ...form, sku: e.target.value })} placeholder="SKU" className={inputCls} />
        <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Name" className={inputCls} />
        <select value={form.feature} onChange={(e) => setForm({ ...form, feature: e.target.value })} className={inputCls}>
          <option value="cv">CV</option><option value="voice_mock">Voice mock</option><option value="mentor">Mentor</option><option value="career_plus">Career+</option>
        </select>
        <select value={form.kind} onChange={(e) => setForm({ ...form, kind: e.target.value })} className={inputCls}>
          <option value="pack">Pack</option><option value="subscription">Subscription</option>
        </select>
        <input required type="number" min="0" value={form.rupees} onChange={(e) => setForm({ ...form, rupees: e.target.value })} placeholder="Price (₹)" className={inputCls} />
        <input type="number" min="0" value={form.grant_amount} onChange={(e) => setForm({ ...form, grant_amount: e.target.value })} placeholder="Credits granted" className={inputCls} />
        <div className="sm:col-span-2">
          <button disabled={createProduct.isPending || !form.sku || !form.name || !form.rupees} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">Add product</button>
        </div>
      </form>

      <div className="mt-4 divide-y divide-line rounded-[14px] border border-line bg-white">
        {(products.data?.data ?? []).map((p) => (
          <div key={p.id} className="flex flex-wrap items-center gap-3 px-5 py-3.5">
            <span className="min-w-40 flex-1">
              <span className="block font-semibold text-ink">{p.name}</span>
              <span className="mono block text-xs text-muted">{p.sku} · {describeKind(p)}</span>
            </span>
            <span className={`mono rounded-full px-2.5 py-0.5 text-[10px] uppercase tracking-widest ${p.active ? "bg-sky text-deep" : "bg-paper text-muted"}`}>{p.active ? "active" : "inactive"}</span>
            {p.active && <button onClick={() => deactivate.mutate(p.id)} className="text-xs font-semibold text-warn hover:underline">Deactivate</button>}
          </div>
        ))}
      </div>
    </div>
  );
}

"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

/**
 * Funding Radar curation (super-admin): post sourced funding/expansion news
 * items that feed the homepage radar. A named company requires its public
 * source URL — the API enforces it; the form says so up front.
 */

type NewsItem = {
  id: number;
  company: string | null;
  sector: string;
  round: string;
  hub: string;
  hiring_lag_months: number;
  roles: string[];
  skills: string[];
  source_name: string;
  source_url: string | null;
  published_on: string;
  active: boolean;
};

const inputCls =
  "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

const EMPTY = {
  company: "",
  sector: "",
  round: "",
  hub: "",
  hiring_lag_months: "3",
  roles: "",
  skills: "",
  source_name: "",
  source_url: "",
  published_on: "",
};

export default function AdminFundingNewsPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState(EMPTY);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "funding-news"],
    queryFn: () => apiJson<{ data: NewsItem[] }>("/api/v1/admin/funding-news"),
  });

  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "funding-news"] });
  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const create = useMutation({
    mutationFn: () =>
      apiJson("/api/v1/admin/funding-news", {
        method: "POST",
        body: JSON.stringify({
          company: form.company.trim() || null,
          sector: form.sector.trim(),
          round: form.round.trim(),
          hub: form.hub.trim(),
          hiring_lag_months: Number(form.hiring_lag_months),
          roles: form.roles.split(",").map((r) => r.trim()).filter(Boolean),
          skills: form.skills.split(",").map((s) => s.trim()).filter(Boolean),
          source_name: form.source_name.trim(),
          source_url: form.source_url.trim() || null,
          published_on: form.published_on,
        }),
      }),
    onSuccess: () => {
      setForm(EMPTY);
      setError(null);
      void refresh();
    },
    onError,
  });

  const deactivate = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/funding-news/${id}`, { method: "DELETE" }),
    onSuccess: () => void refresh(),
    onError,
  });

  const items = data?.data ?? [];
  const set = (k: keyof typeof EMPTY) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  return (
    <div className="space-y-8">
      <div>
        <h1 className="display text-2xl text-ink">Funding Radar</h1>
        <p className="mt-1 text-sm text-ink2/70">
          Sourced public funding &amp; expansion news that powers the homepage radar.
          Naming a company requires its public source link — signals, not guarantees.
        </p>
      </div>

      {/* Add item */}
      <form
        className="grid gap-3 rounded-[14px] border border-line bg-white p-5 shadow-soft md:grid-cols-2"
        onSubmit={(e) => {
          e.preventDefault();
          create.mutate();
        }}
      >
        <input className={inputCls} placeholder="Company (optional — needs source URL)" value={form.company} onChange={set("company")} />
        <input className={inputCls} placeholder="Sector * (e.g. Fintech)" value={form.sector} onChange={set("sector")} required />
        <input className={inputCls} placeholder="Round / event * (e.g. Series C)" value={form.round} onChange={set("round")} required />
        <input className={inputCls} placeholder="Hub * (e.g. Bengaluru)" value={form.hub} onChange={set("hub")} required />
        <input className={inputCls} type="number" min={1} max={12} placeholder="Hiring lag (months) *" value={form.hiring_lag_months} onChange={set("hiring_lag_months")} required />
        <input className={inputCls} type="date" placeholder="Published on *" value={form.published_on} onChange={set("published_on")} required />
        <input className={inputCls} placeholder="Roles * — comma-separated" value={form.roles} onChange={set("roles")} required />
        <input className={inputCls} placeholder="Skills * — comma-separated" value={form.skills} onChange={set("skills")} required />
        <input className={inputCls} placeholder="Source name * (e.g. Economic Times)" value={form.source_name} onChange={set("source_name")} required />
        <input className={inputCls} type="url" placeholder="Source URL (https://…)" value={form.source_url} onChange={set("source_url")} />
        <div className="md:col-span-2 flex items-center justify-between gap-3">
          {error ? <p className="text-sm text-warn">{error}</p> : <span />}
          <button
            type="submit"
            disabled={create.isPending}
            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
          >
            {create.isPending ? "Publishing…" : "Publish to radar"}
          </button>
        </div>
      </form>

      {/* List */}
      {isLoading ? (
        <div className="shimmer h-24 rounded-[14px]" />
      ) : items.length === 0 ? (
        <EmptyState
          title="No radar items yet"
          body="Post the first sourced funding story — the homepage shows curated samples until then."
        />
      ) : (
        <ul className="space-y-3">
          {items.map((n) => (
            <li
              key={n.id}
              className={`rounded-[14px] border border-line bg-white p-5 shadow-soft ${n.active ? "" : "opacity-50"}`}
            >
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <span className="font-semibold text-ink">
                  {n.company ?? n.sector} · {n.round} · {n.hub}
                </span>
                <span className="mono text-xs text-muted">
                  {n.published_on.slice(0, 10)} · ~{n.hiring_lag_months} mo
                </span>
              </div>
              <p className="mono mt-1 text-xs text-muted">
                {n.roles.join(" · ")} — {n.skills.join(", ")}
              </p>
              <div className="mt-2 flex items-center justify-between">
                <span className="mono text-xs text-muted">
                  {n.source_url ? (
                    <a href={n.source_url} target="_blank" rel="noopener noreferrer" className="text-trust hover:underline">
                      {n.source_name} ↗
                    </a>
                  ) : (
                    n.source_name
                  )}
                </span>
                {n.active ? (
                  <button
                    type="button"
                    onClick={() => deactivate.mutate(n.id)}
                    className="text-xs font-semibold text-warn hover:underline"
                  >
                    Remove from radar
                  </button>
                ) : (
                  <span className="mono text-xs text-muted">removed</span>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

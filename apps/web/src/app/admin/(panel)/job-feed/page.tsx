"use client";

import { useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Source = {
  id: number;
  name: string;
  kind: string;
  priority: number;
  is_active: boolean;
  active_items: number;
  last_synced_at: string | null;
};
type Item = {
  id: number;
  title: string;
  company: string;
  location: string | null;
  source_kind: string;
  skills: string[];
  posted_at: string | null;
};
type Payload = { sources: Source[]; recent: Item[] };

const KINDS = [
  { value: "internal", label: "Internal postings" },
  { value: "partner", label: "Hiring-partner feed" },
  { value: "api", label: "Licensed job API" },
  { value: "ats", label: "ATS (Greenhouse/Lever)" },
  { value: "csv", label: "CSV / manual" },
  { value: "scraper", label: "Scraper (Apify actor)" },
];

export default function JobFeedPage() {
  const qc = useQueryClient();
  const [form, setForm] = useState({ name: "", kind: "internal", priority: "10", actorId: "", input: "", exclude: "", token: "" });
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const fileRefs = useRef<Record<number, HTMLInputElement | null>>({});

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "job-feed"],
    queryFn: () => apiJson<{ data: Payload }>("/api/v1/admin/job-feed"),
  });

  const sources = data?.data.sources ?? [];
  const recent = data?.data.recent ?? [];
  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "job-feed"] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");

  const addSource = useMutation({
    mutationFn: () => {
      if (form.kind === "scraper" && form.input) {
        try { JSON.parse(form.input); } catch { throw new ApiError(422, { message: "Actor input must be valid JSON." }); }
      }
      return apiJson("/api/v1/admin/job-feed/sources", {
        method: "POST",
        body: JSON.stringify({
          name: form.name,
          kind: form.kind,
          priority: Number(form.priority) || 0,
          config: form.kind === "scraper"
            ? {
                actor_id: form.actorId,
                input: form.input ? JSON.parse(form.input) : {},
                exclude: form.exclude ? form.exclude.split(",").map((t) => t.trim()).filter(Boolean) : undefined,
                token: form.token.trim() || undefined,
              }
            : undefined,
        }),
      });
    },
    onSuccess: () => { setForm({ name: "", kind: "internal", priority: "10", actorId: "", input: "", exclude: "", token: "" }); setError(null); refresh(); },
    onError,
  });

  const sync = useMutation({
    mutationFn: (id: number) => apiJson<{ data: { ingested: number } }>(`/api/v1/admin/job-feed/sources/${id}/sync`, { method: "POST" }),
    onSuccess: (r) => { setNotice(`Synced — ${r.data.ingested} new item(s).`); setError(null); refresh(); },
    onError,
  });

  const importCsv = useMutation({
    mutationFn: ({ id, file }: { id: number; file: File }) => {
      const fd = new FormData();
      fd.append("file", file);
      return apiJson<{ data: { ingested: number; duplicates: number; skipped: number } }>(`/api/v1/admin/job-feed/sources/${id}/import`, { method: "POST", body: fd });
    },
    onSuccess: (r) => { setNotice(`Imported ${r.data.ingested} · ${r.data.duplicates} dup · ${r.data.skipped} skipped.`); setError(null); refresh(); },
    onError,
  });

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Live job feed</p>
      <h1 className="display mt-2 text-3xl text-ink">Job-feed sources</h1>
      <p className="mt-1 text-sm text-muted">
        Pluggable sources — internal openings, hiring-partner feeds, a licensed API, ATS endpoints, CSV,
        or an Apify scraper actor (Naukri / LinkedIn). Ingested postings run through the same
        skill-extraction pipeline, feed the public job board (7-day window), and appear in every
        student&apos;s &ldquo;Jobs for You&rdquo; feed.
      </p>

      {error && <p className="mt-4 text-sm text-warn">{error}</p>}
      {notice && <p className="mt-4 text-sm text-verify">{notice}</p>}

      {/* New source */}
      <div className="mt-6 rounded-[14px] border border-line bg-white p-5">
        <div className="grid gap-3 sm:grid-cols-3">
          <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Source name" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
          <select value={form.kind} onChange={(e) => setForm({ ...form, kind: e.target.value })} className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust">
            {KINDS.map((k) => <option key={k.value} value={k.value}>{k.label}</option>)}
          </select>
          <input value={form.priority} onChange={(e) => setForm({ ...form, priority: e.target.value })} inputMode="numeric" placeholder="Priority" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
        </div>
        {form.kind === "scraper" && (
          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <input value={form.actorId} onChange={(e) => setForm({ ...form, actorId: e.target.value })} placeholder="Apify actor, e.g. bebity/linkedin-jobs-scraper" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
            <input value={form.input} onChange={(e) => setForm({ ...form, input: e.target.value })} placeholder='Actor input JSON, e.g. {"query":"Data engineer","location":"India"}' className="mono rounded-[10px] border border-line bg-white px-3 py-2 text-xs outline-none focus:border-trust" />
            <input value={form.exclude} onChange={(e) => setForm({ ...form, exclude: e.target.value })} placeholder="Exclude terms, e.g. Salesforce, Frontend, Sales" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
            <input type="password" value={form.token} onChange={(e) => setForm({ ...form, token: e.target.value })} placeholder="Apify token override (optional)" autoComplete="off" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
            <p className="text-xs text-muted sm:col-span-2">
              The query is tightened automatically before each run: a plain role becomes an exact phrase
              (&ldquo;Data engineer&rdquo;) and exclude terms are appended as NOT clauses — so the scrape budget
              goes on relevant roles. Runs on the twice-daily feed sync under the platform Apify token
              (Settings → Job scrapers) — or this source&apos;s own token override, if you bill an actor to a
              different Apify account. Scraped postings expire after 7 days automatically.
            </p>
          </div>
        )}
        <button onClick={() => addSource.mutate()} disabled={!form.name.trim() || addSource.isPending || (form.kind === "scraper" && !form.actorId.trim())} className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {addSource.isPending ? "Adding…" : "Add source"}
        </button>
      </div>

      {/* Sources */}
      {isLoading ? (
        <div className="mt-6 space-y-2">{Array.from({ length: 2 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : sources.length === 0 ? (
        <div className="mt-6"><EmptyState title="No sources yet" body="Add one above — start with your internal openings." /></div>
      ) : (
        <div className="mt-6 space-y-2">
          {sources.map((s) => (
            <div key={s.id} className="rounded-[14px] border border-line bg-white p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-sm font-semibold text-ink">{s.name}</p>
                  <p className="mono text-[11px] uppercase tracking-widest text-muted">
                    {s.kind} · priority {s.priority} · {s.active_items} active
                    {s.last_synced_at && ` · synced ${new Date(s.last_synced_at).toLocaleDateString("en-IN")}`}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  {s.kind === "csv" ? (
                    <label className="cursor-pointer rounded-full border border-line bg-white px-4 py-1.5 text-xs font-semibold text-ink hover:border-trust">
                      Import CSV
                      <input
                        ref={(el) => { fileRefs.current[s.id] = el; }}
                        type="file" accept=".csv,text/csv" className="hidden"
                        onChange={(e) => { const f = e.target.files?.[0]; if (f) importCsv.mutate({ id: s.id, file: f }); }}
                      />
                    </label>
                  ) : (
                    <button onClick={() => sync.mutate(s.id)} disabled={sync.isPending}
                      className="rounded-full border border-line bg-white px-4 py-1.5 text-xs font-semibold text-trust hover:border-trust disabled:opacity-50">
                      {sync.isPending ? "Syncing…" : "Sync now"}
                    </button>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Recent items */}
      <h2 className="mt-10 text-sm font-semibold uppercase tracking-widest text-muted">Recently ingested</h2>
      {recent.length === 0 ? (
        <p className="mt-3 text-sm text-muted">Nothing in the feed yet — sync a source.</p>
      ) : (
        <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
          {recent.map((i) => (
            <div key={i.id} className="px-5 py-3">
              <p className="text-sm text-ink">{i.title} · {i.company}</p>
              <p className="mono text-[11px] uppercase tracking-widest text-muted">
                {[i.location, i.source_kind, i.posted_at].filter(Boolean).join(" · ")}
              </p>
              {i.skills.length > 0 && (
                <div className="mt-1 flex flex-wrap gap-1">
                  {i.skills.slice(0, 10).map((sk) => <span key={sk} className="mono rounded-full bg-sky px-2 py-0.5 text-[10px] text-deep">{sk}</span>)}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

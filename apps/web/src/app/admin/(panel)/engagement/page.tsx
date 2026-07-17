"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type CelebrationRow = {
  id: number;
  student: string;
  display: string;
  display_mode: string;
  role_title: string;
  company: string | null;
  published_at: string | null;
  is_active: boolean;
};
type PulseItemRow = { id: number; title: string; source_name: string; published_on: string; is_active: boolean };
type Digest = { digest_date: string; narrative: string; source: string } | null;
type ContentRow = { id: number; kind: string; title: string; published_at: string; is_active: boolean };

const inputCls =
  "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function AdminEngagementPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);

  const [celebration, setCelebration] = useState({
    student_id: "", display_mode: "named", anonymous_label: "", role_title: "", company: "", consent: false,
  });
  const [pulseItem, setPulseItem] = useState({ title: "", url: "", source_name: "", note: "" });
  const [content, setContent] = useState({ kind: "podcast", title: "", url: "" });

  const celebrations = useQuery({
    queryKey: ["admin", "celebrations"],
    queryFn: () => apiJson<{ data: CelebrationRow[] }>("/api/v1/admin/celebrations"),
  });
  const pulse = useQuery({
    queryKey: ["admin", "pulse"],
    queryFn: () => apiJson<{ data: { items: PulseItemRow[]; digest: Digest } }>("/api/v1/admin/pulse"),
  });
  const contentList = useQuery({
    queryKey: ["admin", "content-hub"],
    queryFn: () => apiJson<{ data: ContentRow[] }>("/api/v1/admin/content-hub"),
  });

  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const act = useMutation({
    mutationFn: ({ path, method = "POST", body }: { path: string; method?: string; body?: object }) =>
      apiJson(path, { method, body: body ? JSON.stringify(body) : undefined }),
    onSuccess: () => {
      setError(null);
      void qc.invalidateQueries({ queryKey: ["admin", "celebrations"] });
      void qc.invalidateQueries({ queryKey: ["admin", "pulse"] });
      void qc.invalidateQueries({ queryKey: ["admin", "content-hub"] });
    },
    onError,
  });

  return (
    <div className="mx-auto max-w-5xl">
      <p className="kicker text-trust">Engagement</p>
      <h1 className="display mt-2 text-3xl text-ink">Celebrations · Market Pulse · Content Hub</h1>

      {error && (
        <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">
          {error} <button onClick={() => setError(null)} className="mono underline">dismiss</button>
        </p>
      )}

      {/* ------------------------------------------------ Celebrations */}
      <section className="mt-8 rounded-[14px] border border-line bg-white p-5">
        <p className="kicker text-verify">Offer celebrations</p>
        <p className="mt-1 text-xs text-muted">
          Broadcasts require the student&apos;s explicit consent — recorded at creation. Publish sends the in-app
          celebration to active students once.
        </p>
        <form
          onSubmit={(e) => {
            e.preventDefault();
            act.mutate(
              { path: "/api/v1/admin/celebrations", body: { ...celebration, student_id: Number(celebration.student_id) } },
            );
            setCelebration({ student_id: "", display_mode: "named", anonymous_label: "", role_title: "", company: "", consent: false });
          }}
          className="mt-4 flex flex-wrap items-end gap-3"
        >
          <input required value={celebration.student_id} onChange={(e) => setCelebration({ ...celebration, student_id: e.target.value })} placeholder="Student ID" className={`${inputCls} mono w-28`} />
          <select value={celebration.display_mode} onChange={(e) => setCelebration({ ...celebration, display_mode: e.target.value })} className={inputCls}>
            <option value="named">Named</option>
            <option value="anonymous">Anonymous</option>
          </select>
          {celebration.display_mode === "anonymous" && (
            <input required value={celebration.anonymous_label} onChange={(e) => setCelebration({ ...celebration, anonymous_label: e.target.value })} placeholder='e.g. "A Data Engineering student from DE-202605"' className={`${inputCls} w-72`} />
          )}
          <input required value={celebration.role_title} onChange={(e) => setCelebration({ ...celebration, role_title: e.target.value })} placeholder="Role title" className={`${inputCls} w-44`} />
          <input value={celebration.company} onChange={(e) => setCelebration({ ...celebration, company: e.target.value })} placeholder="Company (optional)" className={`${inputCls} w-40`} />
          <label className="flex items-center gap-2 text-xs text-muted">
            <input type="checkbox" required checked={celebration.consent} onChange={(e) => setCelebration({ ...celebration, consent: e.target.checked })} className="h-4 w-4 accent-[var(--bj-trust)]" />
            Student consented in writing
          </label>
          <button className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white">Create</button>
        </form>

        <div className="mt-4 divide-y divide-line">
          {celebrations.data?.data.map((c) => (
            <div key={c.id} className="flex flex-wrap items-center gap-3 py-2.5 text-sm">
              <span className="font-semibold text-ink">{c.display}</span>
              <span className="text-muted">{c.role_title}{c.company ? ` · ${c.company}` : ""}</span>
              <span className="mono ml-auto text-xs text-muted">
                {c.published_at ? `published ${c.published_at.slice(0, 10)}` : "draft"}
              </span>
              {!c.published_at && (
                <button
                  onClick={() => act.mutate({ path: `/api/v1/admin/celebrations/${c.id}/publish` })}
                  className="rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white"
                >
                  Publish & broadcast
                </button>
              )}
            </div>
          ))}
        </div>
      </section>

      {/* ------------------------------------------------ Market Pulse */}
      <section className="mt-6 rounded-[14px] border border-line bg-white p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <p className="kicker text-trust">Market Pulse — curated items</p>
          <button
            onClick={() => act.mutate({ path: "/api/v1/admin/pulse/generate" })}
            className="rounded-full border border-line px-4 py-1.5 text-xs font-semibold text-ink hover:border-trust"
          >
            Generate today&apos;s digest
          </button>
        </div>
        <form
          onSubmit={(e) => {
            e.preventDefault();
            act.mutate({ path: "/api/v1/admin/pulse/items", body: pulseItem });
            setPulseItem({ title: "", url: "", source_name: "", note: "" });
          }}
          className="mt-4 flex flex-wrap items-end gap-3"
        >
          <input required value={pulseItem.title} onChange={(e) => setPulseItem({ ...pulseItem, title: e.target.value })} placeholder="Headline" className={`${inputCls} w-64`} />
          <input required value={pulseItem.url} onChange={(e) => setPulseItem({ ...pulseItem, url: e.target.value })} placeholder="Source URL" className={`${inputCls} w-56`} />
          <input required value={pulseItem.source_name} onChange={(e) => setPulseItem({ ...pulseItem, source_name: e.target.value })} placeholder="Source name" className={`${inputCls} w-40`} />
          <button className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white">Add item</button>
        </form>

        {pulse.data?.data.digest && (
          <div className="mt-4 rounded-[10px] bg-paper p-4">
            <p className="mono text-[10px] uppercase tracking-widest text-muted">
              Latest digest · {pulse.data.data.digest.digest_date.slice(0, 10)} · {pulse.data.data.digest.source}
            </p>
            <p className="mt-2 whitespace-pre-line text-sm text-ink2">{pulse.data.data.digest.narrative}</p>
          </div>
        )}

        <div className="mt-3 divide-y divide-line">
          {pulse.data?.data.items.filter((i) => i.is_active).map((i) => (
            <div key={i.id} className="flex items-center gap-3 py-2 text-sm">
              <span className="flex-1 text-ink">{i.title}</span>
              <span className="mono text-xs text-muted">{i.source_name}</span>
              <button onClick={() => act.mutate({ path: `/api/v1/admin/pulse/items/${i.id}`, method: "DELETE" })} className="text-xs text-warn hover:underline">Remove</button>
            </div>
          ))}
        </div>
      </section>

      {/* ------------------------------------------------ Content Hub */}
      <section className="mt-6 rounded-[14px] border border-line bg-white p-5">
        <p className="kicker text-trust">Content Hub — releases</p>
        <p className="mt-1 text-xs text-muted">
          Adding a release notifies active students in-app. YouTube/Instagram auto-ingestion activates once channel
          credentials are configured.
        </p>
        <form
          onSubmit={(e) => {
            e.preventDefault();
            act.mutate({ path: "/api/v1/admin/content-hub", body: content });
            setContent({ kind: "podcast", title: "", url: "" });
          }}
          className="mt-4 flex flex-wrap items-end gap-3"
        >
          <select value={content.kind} onChange={(e) => setContent({ ...content, kind: e.target.value })} className={inputCls}>
            <option value="podcast">Podcast</option>
            <option value="youtube">YouTube</option>
            <option value="instagram">Instagram</option>
          </select>
          <input required value={content.title} onChange={(e) => setContent({ ...content, title: e.target.value })} placeholder="Title" className={`${inputCls} w-72`} />
          <input required value={content.url} onChange={(e) => setContent({ ...content, url: e.target.value })} placeholder="URL" className={`${inputCls} w-56`} />
          <button className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white">Add & notify</button>
        </form>

        <div className="mt-3 divide-y divide-line">
          {contentList.data?.data.filter((i) => i.is_active).map((i) => (
            <div key={i.id} className="flex items-center gap-3 py-2 text-sm">
              <span className="mono text-xs uppercase tracking-widest text-muted">{i.kind}</span>
              <span className="flex-1 text-ink">{i.title}</span>
              <button onClick={() => act.mutate({ path: `/api/v1/admin/content-hub/${i.id}`, method: "DELETE" })} className="text-xs text-warn hover:underline">Remove</button>
            </div>
          ))}
        </div>
      </section>
    </div>
  );
}

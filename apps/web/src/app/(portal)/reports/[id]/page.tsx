"use client";

import Link from "next/link";
import { use, useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { Disclaimer } from "@/components/brand/Disclaimer";

type MasteryRow = { name: string; pct: number; band: "weak" | "developing" | "strong" };
type Report = {
  id: number;
  title: string;
  narrative: string;
  period_start: string | null;
  period_end: string | null;
  insights: {
    mastery?: MasteryRow[];
    next_action?: { title: string; detail: string; href: string | null };
    went_well?: string[];
    needs_work?: string[];
  } | null;
};

const BAND_BAR: Record<string, string> = { weak: "bg-warn", developing: "bg-trust", strong: "bg-verify" };

export default function ReportPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [report, setReport] = useState<Report | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiJson<{ data: Report }>(`/api/v1/me/reports/${id}`)
      .then((r) => setReport(r.data))
      .catch(() => setReport(null))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) return <div className="mx-auto max-w-2xl"><div className="shimmer h-72 rounded-[14px]" /></div>;
  if (!report) return <div className="mx-auto max-w-2xl text-sm text-muted">Report not found.</div>;

  const d = report.insights ?? {};
  const na = d.next_action;

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/reports" className="text-sm text-trust hover:underline">← Weekly reports</Link>
      <h1 className="display mt-3 text-2xl text-ink">{report.title}</h1>
      <p className="mono mt-1 text-[11px] uppercase tracking-widest text-muted">Week of {report.period_start ?? "—"}</p>

      {/* AI narrative */}
      <div className="mt-6 space-y-3 rounded-2xl border border-line bg-white p-6 text-sm leading-relaxed text-ink">
        {report.narrative.split("\n\n").filter(Boolean).map((p, i) => <p key={i}>{p}</p>)}
      </div>

      {/* Next best action */}
      {na && (
        <div className="mt-6 rounded-2xl bg-ink p-6 text-white shadow-lg">
          <p className="kicker text-sky/70">Your one focus</p>
          <h2 className="display mt-2 text-lg">{na.title}</h2>
          {na.detail && <p className="mt-1 text-sky/70">{na.detail}</p>}
          {na.href && (
            <Link href={na.href} className="mt-4 inline-block rounded-full bg-white px-5 py-2 text-sm font-semibold text-ink hover:-translate-y-0.5">Let’s go</Link>
          )}
        </div>
      )}

      {/* Went well / needs work */}
      {(d.went_well?.length || d.needs_work?.length) && (
        <div className="mt-6 grid gap-4 md:grid-cols-2">
          <div className="rounded-2xl border border-trust/30 bg-sky/40 p-5">
            <p className="kicker text-deep">Focus here</p>
            <ul className="mt-2 space-y-1 text-sm text-ink">{(d.needs_work ?? []).map((w, i) => <li key={i}>• {w}</li>)}</ul>
          </div>
          <div className="rounded-2xl border border-verify/25 bg-verify-bg p-5">
            <p className="kicker text-verify">Going well</p>
            <ul className="mt-2 space-y-1 text-sm text-ink">{(d.went_well ?? []).map((w, i) => <li key={i}>• {w}</li>)}</ul>
          </div>
        </div>
      )}

      {/* Mastery */}
      {d.mastery && d.mastery.length > 0 && (
        <div className="mt-6 rounded-2xl border border-line bg-white p-6">
          <p className="kicker text-trust">Mastery map</p>
          <div className="mt-4 space-y-3">
            {d.mastery.map((m, i) => (
              <div key={i}>
                <div className="flex justify-between text-sm text-ink"><span>{m.name}</span><span className="mono text-muted">{m.pct}%</span></div>
                <div className="mt-1 h-2 overflow-hidden rounded-full bg-line"><div className={`h-full rounded-full ${BAND_BAR[m.band]}`} style={{ width: `${m.pct}%` }} /></div>
              </div>
            ))}
          </div>
          <Disclaimer />
        </div>
      )}
    </div>
  );
}

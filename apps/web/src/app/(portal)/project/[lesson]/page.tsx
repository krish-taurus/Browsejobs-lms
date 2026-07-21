"use client";

import { use, useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { ArchitectureView, type Architecture } from "@/components/project/ArchitectureView";

type Project = {
  lesson_id: number;
  title: string | null;
  summary: string | null;
  tech_stack: string[];
  architecture: Architecture | null;
  download_url: string | null;
} | null;

export default function ProjectPage({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const [data, setData] = useState<Project>(null);
  const [loading, setLoading] = useState(true);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    apiJson<{ data: Project }>(`/api/v1/me/lessons/${lesson}/project`)
      .then((r) => setData(r.data))
      .catch(() => setMissing(true))
      .finally(() => setLoading(false));
  }, [lesson]);

  if (loading) return <div className="mx-auto max-w-2xl"><div className="shimmer h-72 rounded-[14px]" /></div>;

  if (missing || !data) {
    return (
      <div className="mx-auto max-w-2xl">
        <h1 className="display text-2xl text-ink">Capstone project</h1>
        <p className="mt-3 text-sm text-muted">This project is being prepared — check back soon.</p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="kicker text-trust">Capstone project</p>
          <h1 className="display mt-2 text-2xl text-ink">{data.title ?? "Project"}</h1>
        </div>
        {data.download_url && (
          <a href={data.download_url} target="_blank" rel="noopener noreferrer"
            className="rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-deep">
            Download architecture
          </a>
        )}
      </div>

      {data.summary && (
        <div className="mt-6 rounded-2xl border border-line bg-white p-6">
          <p className="mono text-[10px] uppercase tracking-widest text-muted">The brief</p>
          <p className="mt-2 whitespace-pre-wrap text-sm text-ink">{data.summary}</p>
        </div>
      )}

      {data.tech_stack.length > 0 && (
        <div className="mt-4">
          <p className="mono text-[10px] uppercase tracking-widest text-muted">Tech stack</p>
          <div className="mt-2 flex flex-wrap gap-1.5">
            {data.tech_stack.map((t) => (
              <span key={t} className="mono rounded-full bg-sky px-2.5 py-1 text-[12px] text-deep">{t}</span>
            ))}
          </div>
        </div>
      )}

      {data.architecture?.layers && data.architecture.layers.length > 0 && (
        <div className="mt-6 rounded-2xl border border-line bg-white p-6">
          <p className="mono text-[10px] uppercase tracking-widest text-muted">Architecture</p>
          <div className="mt-3">
            <ArchitectureView architecture={data.architecture} />
          </div>
        </div>
      )}
    </div>
  );
}

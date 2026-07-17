"use client";

import { use, useEffect, useState } from "react";
import { apiJson } from "@/lib/api";

type SharedCv = {
  title: string;
  content: {
    name?: string | null;
    email?: string | null;
    phone?: string | null;
    headline?: string;
    summary?: string;
    skills?: string[];
    experience?: { title: string; company: string; period?: string | null; bullets: string[] }[];
    projects?: { name: string; bullets: string[] }[];
    education?: { name: string; detail?: string | null }[];
    certifications?: string[];
    links?: { label: string; url: string }[];
  };
  approved: boolean;
  updated_at: string | null;
};

export default function SharedCvPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = use(params);
  const [cv, setCv] = useState<SharedCv | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiJson<{ data: SharedCv }>(`/api/v1/cv/shared/${token}`)
      .then((r) => setCv(r.data))
      .catch(() => setCv(null))
      .finally(() => setLoading(false));
  }, [token]);

  if (loading) {
    return <main className="mx-auto max-w-2xl px-6 py-16"><div className="shimmer h-96 rounded-[14px]" /></main>;
  }

  if (!cv) {
    return (
      <main className="mx-auto max-w-2xl px-6 py-16 text-center">
        <h1 className="display text-2xl text-ink">This CV link is no longer active</h1>
        <p className="mt-2 text-sm text-muted">The owner may have revoked it or generated a newer version.</p>
      </main>
    );
  }

  const c = cv.content;

  return (
    <main className="mx-auto max-w-2xl px-6 py-12">
      <style>{`
        @page { size: A4; margin: 14mm 16mm; }
        @media print {
          .no-print { display: none !important; }
          main { padding: 0 !important; max-width: none !important; }
          .cv-card { border: none !important; box-shadow: none !important; padding: 0 !important; border-radius: 0 !important; }
        }
      `}</style>
      <div className="no-print mb-4 flex justify-end">
        <button
          onClick={() => window.print()}
          className="rounded-full bg-trust px-5 py-1.5 text-xs font-semibold text-white"
        >
          Download PDF
        </button>
      </div>
      <div className="cv-card rounded-[22px] border border-line bg-white p-8 shadow-sm">
        <div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-line pb-4">
          <div>
            <h1 className="display text-2xl text-ink">{c.name}</h1>
            <p className="text-sm font-semibold text-trust">{c.headline}</p>
          </div>
          <div className="text-right">
            {cv.approved && (
              <span className="no-print mono rounded-full bg-verify-bg px-2.5 py-0.5 text-[11px] uppercase tracking-widest text-verify">
                verified by BrowseJobs placement team
              </span>
            )}
            <p className="mono mt-1 text-[10px] text-muted">{c.email} {c.phone && `· ${c.phone}`}</p>
          </div>
        </div>

        {c.summary && <p className="mt-5 text-sm text-ink">{c.summary}</p>}

        {(c.skills?.length ?? 0) > 0 && (
          <>
            <h2 className="mt-6 text-xs font-semibold uppercase tracking-widest text-muted">Skills</h2>
            <div className="mt-2 flex flex-wrap gap-1.5">
              {c.skills!.map((s) => <span key={s} className="rounded-full bg-paper px-2.5 py-0.5 text-xs text-ink">{s}</span>)}
            </div>
          </>
        )}

        {(c.links?.length ?? 0) > 0 && (
          <p className="mono mt-3 text-[11px] text-muted">
            {c.links!.map((l, i) => (
              <span key={i}>{i > 0 && " · "}<a href={l.url} className="text-trust hover:underline" target="_blank" rel="noopener noreferrer">{l.label}</a></span>
            ))}
          </p>
        )}

        {(c.experience?.length ?? 0) > 0 && (
          <>
            <h2 className="mt-6 text-xs font-semibold uppercase tracking-widest text-muted">Experience</h2>
            <div className="mt-2 space-y-3">
              {c.experience!.map((e, i) => (
                <div key={i}>
                  <p className="text-sm font-semibold text-ink">{e.title} — {e.company} <span className="mono text-[10px] text-muted">{e.period}</span></p>
                  <ul className="mt-1 space-y-0.5 text-sm text-ink">
                    {e.bullets.map((b, j) => <li key={j}>• {b}</li>)}
                  </ul>
                </div>
              ))}
            </div>
          </>
        )}

        {(c.projects?.length ?? 0) > 0 && (
          <>
            <h2 className="mt-6 text-xs font-semibold uppercase tracking-widest text-muted">Projects</h2>
            <div className="mt-2 space-y-3">
              {c.projects!.map((p, i) => (
                <div key={i}>
                  <p className="text-sm font-semibold text-ink">{p.name}</p>
                  <ul className="mt-1 space-y-0.5 text-sm text-ink">
                    {p.bullets.map((b, j) => <li key={j}>• {b}</li>)}
                  </ul>
                </div>
              ))}
            </div>
          </>
        )}

        {(c.education?.length ?? 0) > 0 && (
          <>
            <h2 className="mt-6 text-xs font-semibold uppercase tracking-widest text-muted">Education & Training</h2>
            <div className="mt-2 space-y-1">
              {c.education!.map((e, i) => (
                <p key={i} className="text-sm text-ink"><span className="font-semibold">{e.name}</span>{e.detail && ` — ${e.detail}`}</p>
              ))}
            </div>
          </>
        )}

        {(c.certifications?.length ?? 0) > 0 && (
          <>
            <h2 className="mt-6 text-xs font-semibold uppercase tracking-widest text-muted">Certifications</h2>
            <ul className="mt-1 space-y-0.5 text-sm text-ink">
              {c.certifications!.map((x, i) => <li key={i}>• {x}</li>)}
            </ul>
          </>
        )}
      </div>
      <p className="no-print mono mt-4 text-center text-[10px] uppercase tracking-widest text-muted">
        Shared via BrowseJobs · browsejobs.ai
      </p>
    </main>
  );
}

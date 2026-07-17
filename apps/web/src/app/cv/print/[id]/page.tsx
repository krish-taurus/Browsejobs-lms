"use client";

import { use, useEffect, useState } from "react";
import { apiJson } from "@/lib/api";

type CvContent = {
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

type Cv = { id: number; version: number; content: CvContent };

/**
 * Print-perfect A4 CV. Single column, generous whitespace, hairline rules —
 * the layout recruiters expect and every ATS parses. "Save as PDF" via the
 * browser print dialog produces the shareable document.
 */
export default function CvPrintPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [cv, setCv] = useState<Cv | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiJson<{ data: Cv }>(`/api/v1/me/cv/${id}`)
      .then((r) => setCv(r.data))
      .catch(() => setCv(null))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) return null;
  if (!cv) return <main className="p-16 text-center text-sm text-neutral-500">Not found — open this page from your CV.</main>;

  const c = cv.content;
  const contact = [c.email, c.phone, ...(c.links ?? []).map((l) => l.url.replace(/^https?:\/\//, ""))]
    .filter(Boolean)
    .join("  ·  ");

  return (
    <>
      <style>{`
        @page { size: A4; margin: 16mm 18mm; }
        @media print {
          .no-print { display: none !important; }
          body { background: white !important; }
          .cv-sheet { box-shadow: none !important; margin: 0 !important; padding: 0 !important; max-width: none !important; }
          .cv-section { break-inside: avoid; }
        }
        .cv-sheet { font-family: Georgia, 'Times New Roman', serif; color: #111827; }
        .cv-sans { font-family: Arial, Helvetica, sans-serif; }
      `}</style>

      <div className="no-print sticky top-0 z-10 flex items-center justify-center gap-3 border-b border-neutral-200 bg-white/95 px-6 py-3">
        <p className="text-xs text-neutral-500">This is the exact document that prints.</p>
        <button
          onClick={() => window.print()}
          className="rounded-full bg-[#1D4ED8] px-5 py-1.5 text-xs font-semibold text-white"
        >
          Download PDF
        </button>
      </div>

      <main className="cv-sheet mx-auto my-8 max-w-[720px] bg-white px-12 py-10 shadow-lg">
        {/* Header */}
        <header className="text-center">
          <h1 className="text-[26px] font-bold tracking-wide" style={{ letterSpacing: "0.04em" }}>
            {(c.name ?? "").toUpperCase()}
          </h1>
          {c.headline && <p className="cv-sans mt-1 text-[12.5px] font-semibold text-neutral-700">{c.headline}</p>}
          {contact && <p className="cv-sans mt-1.5 text-[10.5px] text-neutral-500">{contact}</p>}
        </header>

        {c.summary && (
          <section className="cv-section mt-5">
            <h2 className="cv-sans border-b border-neutral-300 pb-1 text-[10.5px] font-bold uppercase tracking-[0.18em] text-neutral-700">
              Summary
            </h2>
            <p className="mt-2 text-[11.5px] leading-relaxed">{c.summary}</p>
          </section>
        )}

        {(c.skills?.length ?? 0) > 0 && (
          <section className="cv-section mt-4">
            <h2 className="cv-sans border-b border-neutral-300 pb-1 text-[10.5px] font-bold uppercase tracking-[0.18em] text-neutral-700">
              Skills
            </h2>
            <p className="cv-sans mt-2 text-[11px] leading-relaxed text-neutral-800">{c.skills!.join("  ·  ")}</p>
          </section>
        )}

        {(c.experience?.length ?? 0) > 0 && (
          <section className="cv-section mt-4">
            <h2 className="cv-sans border-b border-neutral-300 pb-1 text-[10.5px] font-bold uppercase tracking-[0.18em] text-neutral-700">
              Experience
            </h2>
            <div className="mt-2 space-y-3">
              {c.experience!.map((e, i) => (
                <div key={i}>
                  <div className="flex items-baseline justify-between">
                    <p className="text-[12px] font-bold">
                      {e.title}
                      <span className="font-normal text-neutral-700"> — {e.company}</span>
                    </p>
                    {e.period && <p className="cv-sans text-[10px] text-neutral-500">{e.period}</p>}
                  </div>
                  <ul className="mt-1 space-y-0.5 pl-4 text-[11.5px] leading-snug">
                    {e.bullets.filter(Boolean).map((b, j) => (
                      <li key={j} className="list-disc">{b}</li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          </section>
        )}

        {(c.projects?.length ?? 0) > 0 && (
          <section className="cv-section mt-4">
            <h2 className="cv-sans border-b border-neutral-300 pb-1 text-[10.5px] font-bold uppercase tracking-[0.18em] text-neutral-700">
              Projects
            </h2>
            <div className="mt-2 space-y-2.5">
              {c.projects!.map((p, i) => (
                <div key={i}>
                  <p className="text-[12px] font-bold">{p.name}</p>
                  <ul className="mt-0.5 space-y-0.5 pl-4 text-[11.5px] leading-snug">
                    {p.bullets.filter(Boolean).map((b, j) => (
                      <li key={j} className="list-disc">{b}</li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          </section>
        )}

        {(c.education?.length ?? 0) > 0 && (
          <section className="cv-section mt-4">
            <h2 className="cv-sans border-b border-neutral-300 pb-1 text-[10.5px] font-bold uppercase tracking-[0.18em] text-neutral-700">
              Education
            </h2>
            <div className="mt-2 space-y-1">
              {c.education!.map((e, i) => (
                <div key={i} className="flex items-baseline justify-between">
                  <p className="text-[11.5px] font-bold">{e.name}</p>
                  {e.detail && <p className="cv-sans text-[10.5px] text-neutral-600">{e.detail}</p>}
                </div>
              ))}
            </div>
          </section>
        )}

        {(c.certifications?.length ?? 0) > 0 && (
          <section className="cv-section mt-4">
            <h2 className="cv-sans border-b border-neutral-300 pb-1 text-[10.5px] font-bold uppercase tracking-[0.18em] text-neutral-700">
              Certifications
            </h2>
            <ul className="mt-2 space-y-0.5 pl-4 text-[11.5px]">
              {c.certifications!.map((x, i) => (
                <li key={i} className="list-disc">{x}</li>
              ))}
            </ul>
          </section>
        )}
      </main>
    </>
  );
}

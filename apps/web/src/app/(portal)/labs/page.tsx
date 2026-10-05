"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Lab = { lesson_id: number; title: string | null; language: string };

/**
 * Trainers name labs "PY-01 — Date Format Conversion". The code is useful for
 * referring to a lab out loud, but inside the title it just pushes the real
 * name to the right, so lift it into its own chip.
 */
function splitTitle(raw: string | null): { code: string | null; name: string } {
  const title = (raw ?? "").trim() || "Coding lab";
  const match = title.match(/^([A-Za-z]{1,5}-\d+)\s*[—–-]\s*(.+)$/);

  return match ? { code: match[1].toUpperCase(), name: match[2] } : { code: null, name: title };
}

function CodeIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-5 shrink-0 text-trust" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
      <path d="M5.5 5.5 2.5 8l3 2.5M10.5 5.5 13.5 8l-3 2.5M9.25 3.25l-2.5 9.5" />
    </svg>
  );
}

function ArrowIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-3.5 shrink-0 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
      <path d="M3 8h9M8.5 4.5 12 8l-3.5 3.5" />
    </svg>
  );
}

export default function LabsPage() {
  const [labs, setLabs] = useState<Lab[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    apiJson<{ data: Lab[] }>("/api/v1/me/labs")
      .then((r) => setLabs(r.data))
      .catch(() => setLabs([]))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  // Group by language — the labs list grows along the skill tracks (Python,
  // SQL, Spark), so the language is the heading a student scans for.
  const groups: { language: string; items: Lab[] }[] = [];
  for (const l of labs) {
    const key = l.language || "Other";
    let g = groups.find((x) => x.language === key);
    if (!g) { g = { language: key, items: [] }; groups.push(g); }
    g.items.push(l);
  }

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Practice</p>
      <h1 className="display mt-2 text-3xl text-ink">Coding labs</h1>
      <p className="mt-2 text-sm text-muted">Write, run, and submit code right in the browser. Every run sharpens your mastery map.</p>

      {loading ? (
        <div className="mt-8 space-y-3">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-[88px] rounded-[14px]" />)}</div>
      ) : labs.length === 0 ? (
        <div className="mt-8"><EmptyState title="No labs yet" body="Coding labs will appear here as your trainer publishes them." /></div>
      ) : (
        <div className="mt-8 space-y-9">
          {groups.map((g) => (
            <section key={g.language}>
              <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 className="display text-lg capitalize text-ink">{g.language}</h2>
                <span className="mono text-xs text-muted">
                  {g.items.length} lab{g.items.length === 1 ? "" : "s"}
                </span>
              </div>

              <div className="mt-3 space-y-3">
                {g.items.map((l) => {
                  const { code, name } = splitTitle(l.title);

                  return (
                    <Link
                      key={l.lesson_id}
                      href={`/labs/${l.lesson_id}`}
                      className="group flex flex-col overflow-hidden rounded-[14px] border border-line bg-surface transition hover:border-trust/50 hover:shadow-soft sm:flex-row"
                    >
                      <div className="flex shrink-0 items-center gap-2.5 border-b border-line bg-paper px-5 py-3 sm:w-[112px] sm:flex-col sm:items-start sm:justify-center sm:gap-2 sm:border-b-0 sm:py-6">
                        <CodeIcon />
                        <span className="mono text-[11px] uppercase leading-none text-muted">{l.language}</span>
                      </div>

                      <div className="flex flex-1 flex-wrap items-center gap-x-4 gap-y-3 border-l-[3px] border-trust px-5 py-4">
                        <div className="min-w-48 flex-1">
                          <div className="flex flex-wrap items-center gap-2">
                            {code && (
                              <span className="mono rounded-full bg-sky px-2 py-0.5 text-[10px] font-semibold uppercase tracking-widest text-deep">{code}</span>
                            )}
                            <h3 className="font-semibold text-ink">{name}</h3>
                          </div>
                        </div>

                        <span className="inline-flex shrink-0 items-center gap-2 rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition group-hover:bg-deep">
                          Open lab
                          <ArrowIcon />
                        </span>
                      </div>
                    </Link>
                  );
                })}
              </div>
            </section>
          ))}
        </div>
      )}
    </div>
  );
}

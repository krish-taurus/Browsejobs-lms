"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";

type Booster = { content: Record<string, unknown>; source: string } | null;
type BoostersPayload = { career_plus: boolean; linkedin: Booster; github: Booster; prep_pack: Booster };

const inputCls = "rounded-full px-5 py-2.5 text-sm font-semibold";

export function CareerBoosters() {
  const [data, setData] = useState<BoostersPayload | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    apiJson<{ data: BoostersPayload }>("/api/v1/me/boosters").then((r) => setData(r.data)).catch(() => setData(null));
  }, []);
  useEffect(() => { load(); }, [load]);

  async function run(kind: "linkedin" | "github" | "prep-pack") {
    setError(null);
    setBusy(kind);
    try {
      await apiJson(`/api/v1/me/boosters/${kind}`, { method: "POST" });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not generate.");
    } finally {
      setBusy(null);
    }
  }

  if (!data) return null;

  return (
    <section className="mt-10 rounded-[22px] border border-line bg-white p-6 md:p-8">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="kicker text-trust">Career+ boosters</p>
          <h2 className="display mt-1 text-xl text-ink">Sharpen your profile for the market</h2>
        </div>
        {!data.career_plus && (
          <Link href="/store" className={`${inputCls} bg-trust text-white`}>Unlock with Career+</Link>
        )}
      </div>

      {!data.career_plus ? (
        <p className="mt-4 text-sm text-muted">
          A LinkedIn rewrite, a GitHub portfolio from your labs, and an interview-day prep pack from real
          interview questions — all built from your own verified work. Included with Career+.
        </p>
      ) : (
        <>
          {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

          <div className="mt-5 grid gap-4 md:grid-cols-3">
            <BoosterCard title="LinkedIn optimizer" desc="A stronger headline and About, from your real facts."
              busy={busy === "linkedin"} onRun={() => run("linkedin")} result={data.linkedin}
              render={(c) => (
                <>
                  <p className="text-sm font-semibold text-ink">{String(c.headline ?? "")}</p>
                  <p className="mt-1 text-xs text-muted">{String(c.about ?? "")}</p>
                </>
              )}
            />
            <BoosterCard title="GitHub portfolio" desc="README write-ups from your passed lab projects."
              busy={busy === "github"} onRun={() => run("github")} result={data.github}
              render={(c) => (
                <ul className="space-y-1.5 text-xs text-ink">
                  {(c.projects as { name: string }[] | undefined)?.slice(0, 4).map((p, i) => (
                    <li key={i}>• {p.name}</li>
                  ))}
                </ul>
              )}
            />
            <BoosterCard title="Interview prep pack" desc="Real questions for your role, grouped by focus."
              busy={busy === "prep-pack"} onRun={() => run("prep-pack")} result={data.prep_pack}
              render={(c) => {
                const areas = (c.focus_areas as { topic: string }[] | undefined) ?? [];
                return areas.length ? (
                  <ul className="space-y-1.5 text-xs text-ink">
                    {areas.slice(0, 4).map((a, i) => <li key={i}>• {a.topic}</li>)}
                  </ul>
                ) : (
                  <p className="text-xs text-muted">No interview-bank coverage for your role yet.</p>
                );
              }}
            />
          </div>
        </>
      )}
    </section>
  );
}

function BoosterCard({
  title, desc, busy, onRun, result, render,
}: {
  title: string;
  desc: string;
  busy: boolean;
  onRun: () => void;
  result: Booster;
  render: (content: Record<string, unknown>) => React.ReactNode;
}) {
  return (
    <div className="flex h-full flex-col rounded-[14px] border border-line bg-paper p-5">
      <p className="font-semibold text-ink">{title}</p>
      <p className="mt-1 flex-1 text-xs text-muted">{desc}</p>
      {result && <div className="mt-3 rounded-[10px] border border-line bg-white p-3">{render(result.content)}</div>}
      <button
        onClick={onRun}
        disabled={busy}
        className="mt-3 rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold text-trust hover:border-trust disabled:opacity-50"
      >
        {busy ? "Generating…" : result ? "Regenerate" : "Generate"}
      </button>
    </div>
  );
}

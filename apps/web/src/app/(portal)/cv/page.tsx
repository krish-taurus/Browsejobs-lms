"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";

type CvContent = {
  name?: string | null;
  email?: string | null;
  phone?: string | null;
  headline?: string;
  summary?: string;
  skills?: string[];
  projects?: { name: string; bullets: string[] }[];
  education?: { name: string; detail: string }[];
  certifications?: string[];
};

type Ats = {
  parse_score: number;
  quantified_pct: number;
  action_verb_pct: number;
  lint: string[];
  jd_match?: { pct: number; matched: string[]; missing: string[] };
};

type Cv = {
  id: number;
  version: number;
  title: string;
  source: string;
  content_source: string;
  status: string;
  content: CvContent;
  ats: Ats | null;
  share_token: string | null;
};

type Topup = { product_id: number; sku: string; name: string; price_paise: number; generations: number };

type Payload = {
  credits: number;
  topups: Topup[];
  latest: Cv | null;
  versions: { id: number; version: number; source: string; status: string; created_at: string | null }[];
};

function scoreTone(n: number): string {
  return n >= 70 ? "text-verify" : n >= 40 ? "text-ink" : "text-warn";
}

export default function CvPage() {
  const [data, setData] = useState<Payload | null>(null);
  const [cv, setCv] = useState<Cv | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [jd, setJd] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(() => {
    apiJson<{ data: Payload }>("/api/v1/me/cv")
      .then((r) => { setData(r.data); setCv(r.data.latest); })
      .catch(() => setData(null))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  async function generate(withJd: boolean) {
    setError(null);
    setNotice(null);
    setBusy(true);
    try {
      const r = await apiJson<{ data: Cv }>("/api/v1/me/cv", {
        method: "POST",
        body: JSON.stringify(withJd && jd.trim() ? { jd } : {}),
      });
      setCv(r.data);
      setNotice(withJd ? "Tailored version created — check the JD match below." : "New version generated.");
      load();
    } catch (err) {
      if (err instanceof ApiError && err.status === 402) {
        setError("You're out of generations — top up below (manual edits stay free).");
      } else {
        setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
      }
    } finally {
      setBusy(false);
    }
  }

  async function checkJd() {
    if (!cv) return;
    setError(null);
    try {
      const r = await apiJson<{ data: Ats }>(`/api/v1/me/cv/${cv.id}/ats`, {
        method: "POST",
        body: JSON.stringify({ jd: jd.trim() || null }),
      });
      setCv({ ...cv, ats: r.data });
      setNotice("Re-scored. The JD check is free — run it as often as you like.");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not score.");
    }
  }

  async function share() {
    if (!cv) return;
    try {
      const r = await apiJson<{ data: { share_url: string } }>(`/api/v1/me/cv/${cv.id}/share`, { method: "POST" });
      await navigator.clipboard.writeText(r.data.share_url).catch(() => undefined);
      setNotice(`Share link copied: ${r.data.share_url}`);
      load();
    } catch {
      setError("Could not create the share link.");
    }
  }

  async function openVersion(id: number) {
    const r = await apiJson<{ data: Cv }>(`/api/v1/me/cv/${id}`);
    setCv(r.data);
  }

  async function buyTopup(t: Topup) {
    setError(null);
    try {
      await apiJson("/api/v1/me/purchases", { method: "POST", body: JSON.stringify({ product_id: t.product_id }) });
      setNotice("Order created — complete the payment from the Store page and your generations land instantly.");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the purchase.");
    }
  }

  if (loading) return <div className="mx-auto max-w-3xl"><div className="shimmer h-64 rounded-[14px]" /></div>;
  if (!data) return <div className="mx-auto max-w-3xl text-sm text-muted">The CV suite isn&apos;t available right now.</div>;

  const c = cv?.content;

  return (
    <div className="mx-auto max-w-3xl">
      <div className="flex items-baseline justify-between">
        <h1 className="display text-2xl text-ink">My CV</h1>
        <span className="mono text-xs text-muted">{data.credits} generation{data.credits === 1 ? "" : "s"} left</span>
      </div>
      <p className="mt-1 text-sm text-muted">
        Built from your real record — modules, graded labs, certificates and interview strengths.
        Your first CV builds itself when you finish the course. Manual edits are always free.
      </p>

      {error && <p className="mt-3 text-sm text-warn">{error}</p>}
      {notice && <p className="mt-3 text-sm text-verify break-all">{notice}</p>}

      <div className="mt-4 flex flex-wrap gap-2">
        <button onClick={() => generate(false)} disabled={busy}
          className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {busy ? "Writing…" : cv ? "Regenerate" : "Generate my CV"}
        </button>
        {cv && (
          <button onClick={share} className="rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-ink hover:border-trust">
            {cv.share_token ? "Copy share link" : "Create share link"}
          </button>
        )}
      </div>

      {data.credits === 0 && data.topups.length > 0 && (
        <div className="mt-3 flex flex-wrap gap-2">
          {data.topups.map((t) => (
            <button key={t.product_id} onClick={() => buyTopup(t)}
              className="rounded-full border border-line bg-white px-4 py-1.5 text-xs font-semibold text-ink hover:border-trust">
              {t.generations} generations · ₹{Math.round(t.price_paise / 100)}
            </button>
          ))}
        </div>
      )}

      {!cv ? (
        <div className="mt-8 rounded-2xl border border-line bg-white p-8 text-center">
          <p className="text-sm text-ink">No CV yet.</p>
          <p className="mt-1 text-sm text-muted">Finish your course for the free auto-build, or generate one now.</p>
        </div>
      ) : (
        <>
          {/* Document */}
          <div className="mt-6 rounded-2xl border border-line bg-white p-6">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
              <div>
                <h2 className="display text-xl text-ink">{c?.name}</h2>
                <p className="text-sm font-semibold text-trust">{c?.headline}</p>
              </div>
              <div className="text-right">
                <span className={`mono rounded-full px-2.5 py-0.5 text-[11px] uppercase tracking-widest ${cv.status === "approved" ? "bg-verify-bg text-verify" : "bg-sky text-deep"}`}>
                  {cv.status === "approved" ? "approved for placement" : `draft · v${cv.version}`}
                </span>
                <p className="mono mt-1 text-[10px] text-muted">{c?.email} {c?.phone && `· ${c.phone}`}</p>
              </div>
            </div>

            {c?.summary && <p className="mt-4 text-sm text-ink">{c.summary}</p>}

            {(c?.skills?.length ?? 0) > 0 && (
              <>
                <h3 className="mt-5 text-xs font-semibold uppercase tracking-widest text-muted">Skills</h3>
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {c!.skills!.map((s) => (
                    <span key={s} className="rounded-full bg-paper px-2.5 py-0.5 text-xs text-ink">{s}</span>
                  ))}
                </div>
              </>
            )}

            {(c?.projects?.length ?? 0) > 0 && (
              <>
                <h3 className="mt-5 text-xs font-semibold uppercase tracking-widest text-muted">Projects</h3>
                <div className="mt-2 space-y-3">
                  {c!.projects!.map((p, i) => (
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

            {(c?.education?.length ?? 0) > 0 && (
              <>
                <h3 className="mt-5 text-xs font-semibold uppercase tracking-widest text-muted">Education & Training</h3>
                <div className="mt-2 space-y-1">
                  {c!.education!.map((e, i) => (
                    <p key={i} className="text-sm text-ink"><span className="font-semibold">{e.name}</span> — {e.detail}</p>
                  ))}
                </div>
              </>
            )}

            {(c?.certifications?.length ?? 0) > 0 && (
              <>
                <h3 className="mt-5 text-xs font-semibold uppercase tracking-widest text-muted">Certifications</h3>
                <ul className="mt-1 space-y-0.5 text-sm text-ink">
                  {c!.certifications!.map((x, i) => <li key={i}>• {x}</li>)}
                </ul>
              </>
            )}
          </div>

          {/* ATS panel */}
          {cv.ats && (
            <div className="mt-4 rounded-2xl border border-line bg-white p-6">
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="text-xs font-semibold uppercase tracking-widest text-muted">ATS check</h3>
                <div className="mono flex gap-4 text-xs">
                  <span className={scoreTone(cv.ats.parse_score)}>parse {cv.ats.parse_score}/100</span>
                  <span className={scoreTone(cv.ats.quantified_pct)}>quantified {cv.ats.quantified_pct}%</span>
                  <span className={scoreTone(cv.ats.action_verb_pct)}>action verbs {cv.ats.action_verb_pct}%</span>
                </div>
              </div>

              {cv.ats.lint.length > 0 && (
                <ul className="mt-3 space-y-1 text-xs text-warn">
                  {cv.ats.lint.map((l, i) => <li key={i}>⚠ {l}</li>)}
                </ul>
              )}

              <textarea value={jd} onChange={(e) => setJd(e.target.value)} rows={3}
                placeholder="Paste a job description to check keyword match — free, unlimited…"
                className="mt-4 w-full resize-y rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust" />
              <div className="mt-2 flex flex-wrap gap-2">
                <button onClick={checkJd} disabled={!jd.trim()}
                  className="rounded-full border border-line bg-white px-4 py-1.5 text-xs font-semibold text-ink hover:border-trust disabled:opacity-50">
                  Check match (free)
                </button>
                <button onClick={() => generate(true)} disabled={busy || !jd.trim()}
                  className="rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white disabled:opacity-50">
                  {busy ? "Tailoring…" : "Tailor CV to this JD (1 credit)"}
                </button>
              </div>

              {cv.ats.jd_match && (
                <div className="mt-3 rounded-[10px] bg-paper p-3 text-xs">
                  <p className="text-ink"><span className="mono font-semibold">{cv.ats.jd_match.pct}% keyword match.</span></p>
                  {cv.ats.jd_match.missing.length > 0 && (
                    <p className="mt-1 text-muted">Missing: {cv.ats.jd_match.missing.join(", ")}</p>
                  )}
                </div>
              )}
            </div>
          )}

          {/* Versions */}
          {data.versions.length > 1 && (
            <div className="mt-4 flex flex-wrap gap-2">
              {data.versions.map((v) => (
                <button key={v.id} onClick={() => openVersion(v.id)}
                  className={`rounded-full px-3 py-1 text-xs ${cv.id === v.id ? "bg-trust text-white" : "border border-line bg-white text-ink"}`}>
                  v{v.version} · {v.source.replace("_", " ")}{v.status === "approved" && " ✓"}
                </button>
              ))}
            </div>
          )}
        </>
      )}
    </div>
  );
}

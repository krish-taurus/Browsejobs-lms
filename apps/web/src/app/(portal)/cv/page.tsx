"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { CareerBoosters } from "@/components/portal/CareerBoosters";

const API = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

type Experience = { title: string; company: string; period?: string | null; bullets: string[] };
type Project = { name: string; bullets: string[] };
type Education = { name: string; detail?: string | null };

type CvContent = {
  name?: string | null;
  email?: string | null;
  phone?: string | null;
  headline?: string;
  summary?: string;
  skills?: string[];
  experience?: Experience[];
  projects?: Project[];
  education?: Education[];
  certifications?: string[];
  links?: { label: string; url: string }[];
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

type Profile = {
  summary: string | null;
  skills: string[];
  experience: Experience[];
  projects: Project[];
  education: Education[];
  certifications: string[];
  links: { label: string; url: string }[];
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
  const [profile, setProfile] = useState<Profile | null>(null);
  const [showProfile, setShowProfile] = useState(false);
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState<CvContent | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<string | null>(null);
  const [jd, setJd] = useState("");
  const [importText, setImportText] = useState("");
  const fileRef = useRef<HTMLInputElement | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(() => {
    Promise.all([
      apiJson<{ data: Payload }>("/api/v1/me/cv"),
      apiJson<{ data: { profile: Profile } }>("/api/v1/me/cv/profile"),
    ])
      .then(([c, p]) => { setData(c.data); setCv(c.data.latest); setProfile(p.data.profile); })
      .catch(() => setData(null))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  function say(ok: string | null, err: string | null) {
    setNotice(ok);
    setError(err);
  }

  async function importCv() {
    say(null, null);
    setBusy("import");
    try {
      const form = new FormData();
      const file = fileRef.current?.files?.[0];
      if (file) form.append("file", file);
      else form.append("text", importText);
      const r = await apiJson<{ data: Profile }>("/api/v1/me/cv/profile/import", { method: "POST", body: form });
      setProfile(r.data);
      setShowProfile(true);
      setImportText("");
      if (fileRef.current) fileRef.current.value = "";
      say("Imported — review your experience and projects below, then generate.", null);
    } catch (err) {
      say(null, err instanceof ApiError ? (err.firstError ?? err.message) : "Could not read that CV.");
    } finally {
      setBusy(null);
    }
  }

  async function saveProfile() {
    if (!profile) return;
    say(null, null);
    setBusy("profile");
    try {
      await apiJson("/api/v1/me/cv/profile", { method: "PUT", body: JSON.stringify(profile) });
      say("Profile saved — the next generation uses it.", null);
    } catch (err) {
      say(null, err instanceof ApiError ? (err.firstError ?? err.message) : "Could not save.");
    } finally {
      setBusy(null);
    }
  }

  async function generate(withJd: boolean) {
    say(null, null);
    setBusy("generate");
    try {
      const r = await apiJson<{ data: Cv }>("/api/v1/me/cv", {
        method: "POST",
        body: JSON.stringify(withJd && jd.trim() ? { jd } : {}),
      });
      setCv(r.data);
      setEditing(false);
      say(withJd ? "Tailored version created — check the JD match below." : "New version generated.", null);
      load();
    } catch (err) {
      if (err instanceof ApiError && err.status === 402) {
        say(null, "You're out of generations — top up below (manual edits stay free).");
      } else {
        say(null, err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
      }
    } finally {
      setBusy(null);
    }
  }

  async function saveEdits() {
    if (!cv || !draft) return;
    setBusy("edit");
    try {
      const r = await apiJson<{ data: Cv }>(`/api/v1/me/cv/${cv.id}`, {
        method: "PATCH",
        body: JSON.stringify({ content: draft }),
      });
      setCv(r.data);
      setEditing(false);
      say("Saved. Edits are free — the ATS score refreshed.", null);
    } catch (err) {
      say(null, err instanceof ApiError ? (err.firstError ?? err.message) : "Could not save.");
    } finally {
      setBusy(null);
    }
  }

  async function checkJd() {
    if (!cv) return;
    const r = await apiJson<{ data: Ats }>(`/api/v1/me/cv/${cv.id}/ats`, {
      method: "POST",
      body: JSON.stringify({ jd: jd.trim() || null }),
    }).catch(() => null);
    if (r) {
      setCv({ ...cv, ats: r.data });
      say("Re-scored — the JD check is free, run it as often as you like.", null);
    }
  }

  async function share() {
    if (!cv) return;
    try {
      const r = await apiJson<{ data: { share_url: string } }>(`/api/v1/me/cv/${cv.id}/share`, { method: "POST" });
      await navigator.clipboard.writeText(r.data.share_url).catch(() => undefined);
      say(`Share link copied: ${r.data.share_url}`, null);
      load();
    } catch {
      say(null, "Could not create the share link.");
    }
  }

  async function openVersion(id: number) {
    const r = await apiJson<{ data: Cv }>(`/api/v1/me/cv/${id}`);
    setCv(r.data);
    setEditing(false);
  }

  async function buyTopup(t: Topup) {
    try {
      await apiJson("/api/v1/me/purchases", { method: "POST", body: JSON.stringify({ product_id: t.product_id }) });
      say("Order created — complete the payment from the Store page and your generations land instantly.", null);
    } catch (err) {
      say(null, err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the purchase.");
    }
  }

  if (loading) return <div className="mx-auto max-w-3xl"><div className="shimmer h-64 rounded-[14px]" /></div>;
  if (!data || !profile) return <div className="mx-auto max-w-3xl text-sm text-muted">The CV suite isn&apos;t available right now.</div>;

  const c = editing ? draft : cv?.content;

  return (
    <div className="mx-auto max-w-3xl">
      <div className="flex items-baseline justify-between">
        <h1 className="display text-2xl text-ink">My CV</h1>
        <span className="mono text-xs text-muted">{data.credits} generation{data.credits === 1 ? "" : "s"} left</span>
      </div>
      <p className="mt-1 text-sm text-muted">
        Your CV, your facts: import your existing CV, add your own projects and experience, and we
        combine them with your graded work here. ATS-safe by construction — and it never mentions
        where you trained.
      </p>

      {error && <p className="mt-3 text-sm text-warn">{error}</p>}
      {notice && <p className="mt-3 text-sm text-verify break-all">{notice}</p>}

      {/* Import + profile */}
      <div className="mt-5 rounded-2xl border border-line bg-white p-5">
        <div className="flex items-center justify-between">
          <h2 className="text-xs font-semibold uppercase tracking-widest text-muted">Your facts (import once, edit any time)</h2>
          <button onClick={() => setShowProfile(!showProfile)} className="text-xs text-trust hover:underline">
            {showProfile ? "Hide" : "Edit profile"}
          </button>
        </div>

        <div className="mt-3 flex flex-wrap items-center gap-2">
          <input ref={fileRef} type="file" accept=".txt,.md,.docx" className="text-xs text-muted" />
          <span className="text-xs text-muted">or</span>
          <input
            value={importText}
            onChange={(e) => setImportText(e.target.value)}
            placeholder="paste your current CV text…"
            className="min-w-40 flex-1 rounded-[10px] border border-line bg-white px-3 py-1.5 text-xs text-ink outline-none focus:border-trust"
          />
          <button onClick={importCv} disabled={busy === "import"}
            className="rounded-full border border-line bg-white px-4 py-1.5 text-xs font-semibold text-ink hover:border-trust disabled:opacity-50">
            {busy === "import" ? "Reading…" : "Import my CV"}
          </button>
        </div>

        {showProfile && (
          <div className="mt-4 space-y-4 border-t border-line pt-4">
            <div>
              <p className="text-xs font-semibold text-ink">Work experience</p>
              {profile.experience.map((e, i) => (
                <div key={i} className="mt-2 rounded-[10px] bg-paper p-3">
                  <div className="grid gap-2 sm:grid-cols-3">
                    <input value={e.title} placeholder="Title" onChange={(ev) => setProfile({ ...profile, experience: profile.experience.map((x, j) => j === i ? { ...x, title: ev.target.value } : x) })}
                      className="rounded-[8px] border border-line bg-white px-2 py-1 text-xs" />
                    <input value={e.company} placeholder="Company" onChange={(ev) => setProfile({ ...profile, experience: profile.experience.map((x, j) => j === i ? { ...x, company: ev.target.value } : x) })}
                      className="rounded-[8px] border border-line bg-white px-2 py-1 text-xs" />
                    <input value={e.period ?? ""} placeholder="Dates (e.g. 2022 – 2024)" onChange={(ev) => setProfile({ ...profile, experience: profile.experience.map((x, j) => j === i ? { ...x, period: ev.target.value } : x) })}
                      className="rounded-[8px] border border-line bg-white px-2 py-1 text-xs" />
                  </div>
                  <textarea value={e.bullets.join("\n")} rows={2} placeholder="One achievement per line"
                    onChange={(ev) => setProfile({ ...profile, experience: profile.experience.map((x, j) => j === i ? { ...x, bullets: ev.target.value.split("\n") } : x) })}
                    className="mt-2 w-full rounded-[8px] border border-line bg-white px-2 py-1 text-xs" />
                  <button onClick={() => setProfile({ ...profile, experience: profile.experience.filter((_, j) => j !== i) })} className="mt-1 text-[11px] text-warn hover:underline">Remove</button>
                </div>
              ))}
              <button onClick={() => setProfile({ ...profile, experience: [...profile.experience, { title: "", company: "", period: "", bullets: [] }] })}
                className="mt-2 text-xs font-semibold text-trust hover:underline">+ Add experience</button>
            </div>

            <div>
              <p className="text-xs font-semibold text-ink">Your own projects</p>
              {profile.projects.map((p, i) => (
                <div key={i} className="mt-2 rounded-[10px] bg-paper p-3">
                  <input value={p.name} placeholder="Project name (tech stack)" onChange={(ev) => setProfile({ ...profile, projects: profile.projects.map((x, j) => j === i ? { ...x, name: ev.target.value } : x) })}
                    className="w-full rounded-[8px] border border-line bg-white px-2 py-1 text-xs" />
                  <textarea value={p.bullets.join("\n")} rows={2} placeholder="One achievement per line"
                    onChange={(ev) => setProfile({ ...profile, projects: profile.projects.map((x, j) => j === i ? { ...x, bullets: ev.target.value.split("\n") } : x) })}
                    className="mt-2 w-full rounded-[8px] border border-line bg-white px-2 py-1 text-xs" />
                  <button onClick={() => setProfile({ ...profile, projects: profile.projects.filter((_, j) => j !== i) })} className="mt-1 text-[11px] text-warn hover:underline">Remove</button>
                </div>
              ))}
              <button onClick={() => setProfile({ ...profile, projects: [...profile.projects, { name: "", bullets: [] }] })}
                className="mt-2 text-xs font-semibold text-trust hover:underline">+ Add project</button>
            </div>

            <div>
              <p className="text-xs font-semibold text-ink">Education</p>
              {profile.education.map((e, i) => (
                <div key={i} className="mt-2 flex flex-wrap gap-2">
                  <input value={e.name} placeholder="Qualification" onChange={(ev) => setProfile({ ...profile, education: profile.education.map((x, j) => j === i ? { ...x, name: ev.target.value } : x) })}
                    className="min-w-32 flex-1 rounded-[8px] border border-line bg-white px-2 py-1 text-xs" />
                  <input value={e.detail ?? ""} placeholder="Institution, year" onChange={(ev) => setProfile({ ...profile, education: profile.education.map((x, j) => j === i ? { ...x, detail: ev.target.value } : x) })}
                    className="min-w-32 flex-1 rounded-[8px] border border-line bg-white px-2 py-1 text-xs" />
                  <button onClick={() => setProfile({ ...profile, education: profile.education.filter((_, j) => j !== i) })} className="text-[11px] text-warn hover:underline">Remove</button>
                </div>
              ))}
              <button onClick={() => setProfile({ ...profile, education: [...profile.education, { name: "", detail: "" }] })}
                className="mt-2 text-xs font-semibold text-trust hover:underline">+ Add education</button>
            </div>

            <button onClick={saveProfile} disabled={busy === "profile"}
              className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
              {busy === "profile" ? "Saving…" : "Save profile"}
            </button>
          </div>
        )}
      </div>

      {/* Actions */}
      <div className="mt-4 flex flex-wrap gap-2">
        <button onClick={() => generate(false)} disabled={busy === "generate"}
          className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {busy === "generate" ? "Writing…" : cv ? "Regenerate" : "Generate my CV"}
        </button>
        {cv && !editing && (
          <button onClick={() => { setDraft(JSON.parse(JSON.stringify(cv.content))); setEditing(true); }}
            className="rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-ink hover:border-trust">
            Edit (free)
          </button>
        )}
        {cv && editing && (
          <>
            <button onClick={saveEdits} disabled={busy === "edit"}
              className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
              {busy === "edit" ? "Saving…" : "Save edits"}
            </button>
            <button onClick={() => setEditing(false)} className="rounded-full border border-line bg-white px-5 py-2 text-sm text-ink">Cancel</button>
          </>
        )}
        {cv && !editing && (
          <>
            <a href={`/cv/print/${cv.id}`} target="_blank" rel="noopener noreferrer"
              className="rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-ink hover:border-trust">
              Download PDF
            </a>
            <button onClick={share} className="rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-ink hover:border-trust">
              {cv.share_token ? "Copy share link" : "Create share link"}
            </button>
            <a href={`${API}/api/v1/me/cv/${cv.id}/ats-text`}
              className="rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-ink hover:border-trust">
              ATS .txt
            </a>
          </>
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
          <p className="mt-1 text-sm text-muted">Import your existing CV above, add your facts, then generate — or finish your course for the free auto-build.</p>
        </div>
      ) : (
        <>
          {/* Document */}
          <div className="mt-6 rounded-2xl border border-line bg-white p-6">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
              <div className="min-w-0 flex-1">
                <h2 className="display text-xl text-ink">{cv.content.name}</h2>
                {editing ? (
                  <input value={draft?.headline ?? ""} onChange={(e) => setDraft({ ...draft!, headline: e.target.value })}
                    className="mt-1 w-full rounded-[8px] border border-line bg-white px-2 py-1 text-sm font-semibold text-trust" />
                ) : (
                  <p className="text-sm font-semibold text-trust">{c?.headline}</p>
                )}
              </div>
              <div className="text-right">
                <span className={`mono rounded-full px-2.5 py-0.5 text-[11px] uppercase tracking-widest ${cv.status === "approved" ? "bg-verify-bg text-verify" : "bg-sky text-deep"}`}>
                  {cv.status === "approved" ? "approved for placement" : `draft · v${cv.version}`}
                </span>
                <p className="mono mt-1 text-[10px] text-muted">{cv.content.email} {cv.content.phone && `· ${cv.content.phone}`}</p>
              </div>
            </div>

            {editing ? (
              <textarea value={draft?.summary ?? ""} rows={3} onChange={(e) => setDraft({ ...draft!, summary: e.target.value })}
                className="mt-4 w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink" />
            ) : (
              c?.summary && <p className="mt-4 text-sm text-ink">{c.summary}</p>
            )}

            <h3 className="mt-5 text-xs font-semibold uppercase tracking-widest text-muted">Skills</h3>
            {editing ? (
              <input value={(draft?.skills ?? []).join(", ")}
                onChange={(e) => setDraft({ ...draft!, skills: e.target.value.split(",").map((s) => s.trim()).filter(Boolean) })}
                className="mt-2 w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink" />
            ) : (
              <div className="mt-2 flex flex-wrap gap-1.5">
                {(c?.skills ?? []).map((s) => <span key={s} className="rounded-full bg-paper px-2.5 py-0.5 text-xs text-ink">{s}</span>)}
              </div>
            )}

            {(c?.experience?.length ?? 0) > 0 && (
              <>
                <h3 className="mt-5 text-xs font-semibold uppercase tracking-widest text-muted">Experience</h3>
                <div className="mt-2 space-y-3">
                  {c!.experience!.map((e, i) => (
                    <div key={i}>
                      <p className="text-sm font-semibold text-ink">{e.title} — {e.company} <span className="mono text-[10px] text-muted">{e.period}</span></p>
                      {editing ? (
                        <textarea value={(draft?.experience?.[i]?.bullets ?? []).join("\n")} rows={2}
                          onChange={(ev) => setDraft({ ...draft!, experience: draft!.experience!.map((x, j) => j === i ? { ...x, bullets: ev.target.value.split("\n") } : x) })}
                          className="mt-1 w-full rounded-[8px] border border-line bg-white px-2 py-1 text-xs" />
                      ) : (
                        <ul className="mt-1 space-y-0.5 text-sm text-ink">{e.bullets.map((b, j) => <li key={j}>• {b}</li>)}</ul>
                      )}
                    </div>
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
                      {editing ? (
                        <>
                          <input value={draft?.projects?.[i]?.name ?? ""}
                            onChange={(ev) => setDraft({ ...draft!, projects: draft!.projects!.map((x, j) => j === i ? { ...x, name: ev.target.value } : x) })}
                            className="w-full rounded-[8px] border border-line bg-white px-2 py-1 text-sm font-semibold" />
                          <textarea value={(draft?.projects?.[i]?.bullets ?? []).join("\n")} rows={2}
                            onChange={(ev) => setDraft({ ...draft!, projects: draft!.projects!.map((x, j) => j === i ? { ...x, bullets: ev.target.value.split("\n") } : x) })}
                            className="mt-1 w-full rounded-[8px] border border-line bg-white px-2 py-1 text-xs" />
                          <button onClick={() => setDraft({ ...draft!, projects: draft!.projects!.filter((_, j) => j !== i) })} className="mt-1 text-[11px] text-warn hover:underline">Remove project</button>
                        </>
                      ) : (
                        <>
                          <p className="text-sm font-semibold text-ink">{p.name}</p>
                          <ul className="mt-1 space-y-0.5 text-sm text-ink">{p.bullets.map((b, j) => <li key={j}>• {b}</li>)}</ul>
                        </>
                      )}
                    </div>
                  ))}
                </div>
                {editing && (
                  <button onClick={() => setDraft({ ...draft!, projects: [...(draft!.projects ?? []), { name: "New project", bullets: [] }] })}
                    className="mt-2 text-xs font-semibold text-trust hover:underline">+ Add project</button>
                )}
              </>
            )}

            {(c?.education?.length ?? 0) > 0 && (
              <>
                <h3 className="mt-5 text-xs font-semibold uppercase tracking-widest text-muted">Education</h3>
                <div className="mt-2 space-y-1">
                  {c!.education!.map((e, i) => (
                    <p key={i} className="text-sm text-ink"><span className="font-semibold">{e.name}</span>{e.detail && ` — ${e.detail}`}</p>
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
          {cv.ats && !editing && (
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
                <button onClick={() => generate(true)} disabled={busy === "generate" || !jd.trim()}
                  className="rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white disabled:opacity-50">
                  {busy === "generate" ? "Tailoring…" : "Tailor CV to this JD (1 credit)"}
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

      <CareerBoosters />
    </div>
  );
}

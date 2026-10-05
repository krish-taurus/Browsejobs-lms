"use client";

import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";

type Experience = { title: string; company: string; period?: string | null; bullets: string[] };
type Project = { name: string; bullets: string[] };
type Education = { name: string; detail?: string | null };

type CvContent = {
  headline?: string;
  summary?: string;
  skills?: string[];
  experience?: Experience[];
  projects?: Project[];
  education?: Education[];
  certifications?: string[];
};

type Ats = {
  parse_score: number;
  quantified_pct: number;
  action_verb_pct: number;
};

type CvVersion = { id: number; content: CvContent; ats: Ats | null };

/**
 * Word-level diff (Myers/LCS on whitespace-split tokens). Kept to plain
 * strings — headline and summary are the only fields short enough that a
 * reader benefits from seeing exactly which words moved, rather than just
 * "this changed".
 */
function wordDiff(a: string, b: string): { text: string; type: "same" | "add" | "del" }[] {
  const aw = a.split(/(\s+)/).filter((t) => t !== "");
  const bw = b.split(/(\s+)/).filter((t) => t !== "");
  const m = aw.length;
  const n = bw.length;
  const dp: number[][] = Array.from({ length: m + 1 }, () => new Array(n + 1).fill(0));
  for (let i = m - 1; i >= 0; i--) {
    for (let j = n - 1; j >= 0; j--) {
      dp[i][j] = aw[i] === bw[j] ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
    }
  }
  const out: { text: string; type: "same" | "add" | "del" }[] = [];
  let i = 0;
  let j = 0;
  while (i < m && j < n) {
    if (aw[i] === bw[j]) {
      out.push({ text: aw[i], type: "same" });
      i++;
      j++;
    } else if (dp[i + 1][j] >= dp[i][j + 1]) {
      out.push({ text: aw[i], type: "del" });
      i++;
    } else {
      out.push({ text: bw[j], type: "add" });
      j++;
    }
  }
  while (i < m) out.push({ text: aw[i++], type: "del" });
  while (j < n) out.push({ text: bw[j++], type: "add" });
  return out;
}

function WordDiff({ before, after }: { before: string; after: string }) {
  if (before === after) return <p className="text-sm text-ink">{after}</p>;
  return (
    <p className="text-sm leading-relaxed text-ink">
      {wordDiff(before, after).map((tok, i) => {
        if (tok.type === "same") return <span key={i}>{tok.text}</span>;
        if (tok.type === "add") return <span key={i} className="rounded bg-verify-bg text-verify">{tok.text}</span>;
        return <span key={i} className="rounded bg-warn/10 text-warn line-through">{tok.text}</span>;
      })}
    </p>
  );
}

/** Set-style diff for flat lists (skills, certifications, single bullets). */
function listDiff(before: string[], after: string[]) {
  const removed = before.filter((x) => !after.includes(x));
  const kept = after.filter((x) => before.includes(x));
  const added = after.filter((x) => !before.includes(x));
  return { removed, kept, added };
}

function ChipList({ items, tone }: { items: string[]; tone: "kept" | "added" | "removed" }) {
  if (items.length === 0) return null;
  const cls =
    tone === "added"
      ? "bg-verify-bg text-verify"
      : tone === "removed"
        ? "bg-warn/10 text-warn line-through"
        : "bg-paper text-muted";
  return (
    <div className="flex flex-wrap gap-1.5">
      {items.map((x) => (
        <span key={x} className={`mono rounded-full px-2.5 py-0.5 text-[11px] ${cls}`}>
          {tone === "added" ? "+ " : tone === "removed" ? "− " : ""}
          {x}
        </span>
      ))}
    </div>
  );
}

function BulletDiff({ before, after }: { before: string[]; after: string[] }) {
  const { removed, kept, added } = listDiff(before, after);
  return (
    <ul className="mt-1 space-y-0.5 text-sm">
      {kept.map((b, i) => <li key={`k${i}`} className="text-ink">• {b}</li>)}
      {added.map((b, i) => <li key={`a${i}`} className="rounded bg-verify-bg px-1 text-verify">+ {b}</li>)}
      {removed.map((b, i) => <li key={`r${i}`} className="rounded bg-warn/10 px-1 text-warn line-through">− {b}</li>)}
    </ul>
  );
}

function ScoreRow({ label, before, after }: { label: string; before: number; after: number }) {
  const delta = after - before;
  const tone = delta > 0 ? "text-verify" : delta < 0 ? "text-warn" : "text-muted";
  return (
    <div className="flex items-center justify-between py-2 text-sm">
      <span className="text-muted">{label}</span>
      <span className="mono">
        {before} → <span className="font-semibold text-ink">{after}</span>{" "}
        <span className={tone}>({delta > 0 ? "+" : ""}{delta})</span>
      </span>
    </div>
  );
}

/**
 * Shows exactly what an AI generation or revision changed, so "we made your
 * CV more ATS-friendly" is a diff someone can check, not a claim they take
 * on faith. Fetches the previous version once and compares in the browser —
 * nothing is re-scored or re-generated to build this view.
 */
export function CvCompare({ currentId, previousId }: { currentId: number; previousId: number }) {
  const [current, setCurrent] = useState<CvVersion | null>(null);
  const [previous, setPrevious] = useState<CvVersion | null>(null);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    setCurrent(null);
    setPrevious(null);
  }, [currentId, previousId]);

  async function load() {
    setOpen(true);
    if (current && previous) return;
    setLoading(true);
    try {
      const [c, p] = await Promise.all([
        apiJson<{ data: CvVersion }>(`/api/v1/me/cv/${currentId}`),
        apiJson<{ data: CvVersion }>(`/api/v1/me/cv/${previousId}`),
      ]);
      setCurrent(c.data);
      setPrevious(p.data);
    } catch {
      setOpen(false);
    } finally {
      setLoading(false);
    }
  }

  if (!open) {
    return (
      <button
        onClick={load}
        disabled={loading}
        className="mt-3 rounded-full border border-line bg-white px-4 py-2 text-xs font-semibold text-trust hover:border-trust disabled:opacity-50"
      >
        {loading ? "Loading…" : "See what changed →"}
      </button>
    );
  }

  if (!current || !previous) {
    return <div className="mt-3 shimmer h-32 rounded-[14px]" />;
  }

  const before = previous.content;
  const after = current.content;

  const expBefore = before.experience ?? [];
  const expAfter = after.experience ?? [];
  const matchedExp = expAfter.map((e) => ({
    entry: e,
    prior: expBefore.find((x) => x.title === e.title && x.company === e.company) ?? null,
  }));
  const droppedExp = expBefore.filter((e) => !expAfter.some((x) => x.title === e.title && x.company === e.company));

  const projBefore = before.projects ?? [];
  const projAfter = after.projects ?? [];
  const matchedProj = projAfter.map((p) => ({
    entry: p,
    prior: projBefore.find((x) => x.name === p.name) ?? null,
  }));
  const droppedProj = projBefore.filter((p) => !projAfter.some((x) => x.name === p.name));

  const skillsDiff = listDiff(before.skills ?? [], after.skills ?? []);
  const certsDiff = listDiff(before.certifications ?? [], after.certifications ?? []);

  return (
    <div className="mt-3 rounded-2xl border border-line bg-white p-5">
      <div className="flex items-center justify-between">
        <p className="kicker text-trust">Compare with the previous version</p>
        <button onClick={() => setOpen(false)} className="text-xs text-muted hover:text-ink">Hide</button>
      </div>

      {current.ats && previous.ats && (
        <div className="mt-3 divide-y divide-line rounded-[12px] border border-line bg-paper px-4">
          <ScoreRow label="ATS parse score" before={previous.ats.parse_score} after={current.ats.parse_score} />
          <ScoreRow label="Quantified bullets" before={previous.ats.quantified_pct} after={current.ats.quantified_pct} />
          <ScoreRow label="Action-verb bullets" before={previous.ats.action_verb_pct} after={current.ats.action_verb_pct} />
        </div>
      )}

      {before.headline !== after.headline && (
        <div className="mt-4">
          <p className="text-xs font-semibold uppercase tracking-widest text-muted">Headline</p>
          <WordDiff before={before.headline ?? ""} after={after.headline ?? ""} />
        </div>
      )}

      {before.summary !== after.summary && (
        <div className="mt-4">
          <p className="text-xs font-semibold uppercase tracking-widest text-muted">Summary</p>
          <WordDiff before={before.summary ?? ""} after={after.summary ?? ""} />
        </div>
      )}

      {(skillsDiff.added.length > 0 || skillsDiff.removed.length > 0) && (
        <div className="mt-4">
          <p className="text-xs font-semibold uppercase tracking-widest text-muted">Skills</p>
          <div className="mt-1.5 space-y-1.5">
            <ChipList items={skillsDiff.kept} tone="kept" />
            <ChipList items={skillsDiff.added} tone="added" />
            <ChipList items={skillsDiff.removed} tone="removed" />
          </div>
        </div>
      )}

      {matchedExp.some(({ entry, prior }) => !prior || JSON.stringify(prior.bullets) !== JSON.stringify(entry.bullets)) && (
        <div className="mt-4">
          <p className="text-xs font-semibold uppercase tracking-widest text-muted">Experience</p>
          {matchedExp.map(({ entry, prior }) => (
            <div key={`${entry.title}-${entry.company}`} className="mt-2">
              <p className="text-sm font-semibold text-ink">
                {entry.title} — {entry.company}
                {!prior && <span className="mono ml-2 rounded-full bg-verify-bg px-2 py-0.5 text-[10px] text-verify">new role</span>}
              </p>
              <BulletDiff before={prior?.bullets ?? []} after={entry.bullets} />
            </div>
          ))}
          {droppedExp.map((e) => (
            <div key={`${e.title}-${e.company}-dropped`} className="mt-2">
              <p className="text-sm font-semibold text-warn line-through">{e.title} — {e.company}</p>
              <p className="text-xs text-warn">Removed from this version.</p>
            </div>
          ))}
        </div>
      )}

      {matchedProj.some(({ entry, prior }) => !prior || JSON.stringify(prior.bullets) !== JSON.stringify(entry.bullets)) && (
        <div className="mt-4">
          <p className="text-xs font-semibold uppercase tracking-widest text-muted">Projects</p>
          {matchedProj.map(({ entry, prior }) => (
            <div key={entry.name} className="mt-2">
              <p className="text-sm font-semibold text-ink">
                {entry.name}
                {!prior && <span className="mono ml-2 rounded-full bg-verify-bg px-2 py-0.5 text-[10px] text-verify">new project</span>}
              </p>
              <BulletDiff before={prior?.bullets ?? []} after={entry.bullets} />
            </div>
          ))}
          {droppedProj.map((p) => (
            <div key={`${p.name}-dropped`} className="mt-2">
              <p className="text-sm font-semibold text-warn line-through">{p.name}</p>
              <p className="text-xs text-warn">Removed from this version.</p>
            </div>
          ))}
        </div>
      )}

      {(certsDiff.added.length > 0 || certsDiff.removed.length > 0) && (
        <div className="mt-4">
          <p className="text-xs font-semibold uppercase tracking-widest text-muted">Certifications</p>
          <div className="mt-1.5 space-y-1.5">
            <ChipList items={certsDiff.kept} tone="kept" />
            <ChipList items={certsDiff.added} tone="added" />
            <ChipList items={certsDiff.removed} tone="removed" />
          </div>
        </div>
      )}

      <p className="mt-4 text-[11px] leading-relaxed text-muted">
        Green = added, red strikethrough = removed, plain = unchanged.
      </p>
    </div>
  );
}

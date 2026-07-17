"use client";

import Link from "next/link";
import { use, useCallback, useEffect, useRef, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { Disclaimer } from "@/components/brand/Disclaimer";

type Criterion = { criterion: string; max_points: number };
type GradeCriterion = { criterion: string; score: number; note: string };
type Assignment = {
  lesson_id: number;
  title: string;
  instructions: string | null;
  allow_link: boolean;
  allow_file: boolean;
  max_points: number;
  rubric: Criterion[];
  submission: { body: string | null; link: string | null; file: { name: string | null; url: string | null } | null; status: string; submitted_at: string | null } | null;
  grade: { score: number; max_points: number; feedback: string | null; criteria: GradeCriterion[] | null } | null;
};

export default function AssignmentPage({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const [data, setData] = useState<Assignment | null>(null);
  const [body, setBody] = useState("");
  const [link, setLink] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);

  const load = useCallback(() => {
    apiJson<{ data: Assignment }>(`/api/v1/me/assignments/${lesson}`)
      .then((r) => { setData(r.data); setBody(r.data.submission?.body ?? ""); setLink(r.data.submission?.link ?? ""); })
      .catch(() => setError("This assignment could not be loaded."));
  }, [lesson]);

  useEffect(() => { load(); }, [load]);

  async function submit() {
    setError(null);
    setBusy(true);
    try {
      const fd = new FormData();
      if (body.trim()) fd.append("body", body);
      if (link.trim()) fd.append("link", link);
      const f = fileRef.current?.files?.[0];
      if (f) fd.append("file", f);
      await apiJson(`/api/v1/me/assignments/${lesson}/submit`, { method: "POST", body: fd });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not submit.");
    } finally {
      setBusy(false);
    }
  }

  if (error && !data) return <div className="mx-auto max-w-2xl text-sm text-muted">{error}</div>;
  if (!data) return <div className="mx-auto max-w-2xl"><div className="shimmer h-72 rounded-[14px]" /></div>;

  const graded = data.grade !== null;
  const submitted = data.submission !== null;
  const locked = graded; // a released grade locks resubmission

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/grades" className="text-sm text-trust hover:underline">← My grades</Link>
      <h1 className="display mt-3 text-2xl text-ink">{data.title}</h1>
      {data.instructions && <p className="mt-3 rounded-[10px] border-l-2 border-trust bg-sky/40 px-3 py-2 text-sm text-ink">{data.instructions}</p>}

      {/* Rubric */}
      {data.rubric.length > 0 && (
        <div className="mt-4 rounded-[14px] border border-line bg-white p-4">
          <p className="kicker text-trust">Graded on</p>
          <ul className="mt-2 space-y-1">
            {data.rubric.map((r, i) => (
              <li key={i} className="flex justify-between text-sm text-ink"><span>{r.criterion}</span><span className="mono text-muted">/{r.max_points}</span></li>
            ))}
          </ul>
        </div>
      )}

      {/* Released grade */}
      {graded && data.grade && (
        <div className="mt-6 rounded-2xl border border-verify/30 bg-verify-bg p-6">
          <p className="kicker text-verify">Your grade</p>
          <div className="mono mt-1 text-4xl font-bold text-ink">{data.grade.score}<span className="text-xl text-muted">/{data.grade.max_points}</span></div>
          {data.grade.feedback && <p className="mt-3 text-sm text-ink">{data.grade.feedback}</p>}
          {data.grade.criteria && (
            <ul className="mt-3 space-y-1">
              {data.grade.criteria.map((c, i) => (
                <li key={i} className="text-sm text-ink"><span className="font-semibold">{c.criterion}: {c.score}</span>{c.note ? ` — ${c.note}` : ""}</li>
              ))}
            </ul>
          )}
          <Disclaimer />
        </div>
      )}

      {/* Awaiting review */}
      {submitted && !graded && (
        <p className="mt-6 rounded-[10px] bg-sky px-3 py-2 text-sm text-deep">Submitted — your trainer is reviewing it. You&apos;ll be notified when your grade is ready.</p>
      )}

      {/* Submission form */}
      {!locked && (
        <div className="mt-6 rounded-2xl border border-line bg-white p-5">
          <p className="text-sm font-semibold text-ink">{submitted ? "Update your submission" : "Submit your work"}</p>
          <textarea value={body} onChange={(e) => setBody(e.target.value)} rows={5} placeholder="Write your response…" className="mt-3 w-full resize-none rounded-[10px] border border-line bg-paper px-3 py-2 text-sm text-ink outline-none focus:border-trust" />
          {data.allow_link && <input value={link} onChange={(e) => setLink(e.target.value)} placeholder="Link (GitHub repo, deployed app…)" className="mt-3 w-full rounded-[10px] border border-line bg-paper px-3 py-2 text-sm text-ink outline-none focus:border-trust" />}
          {data.allow_file && (
            <div className="mt-3">
              <input ref={fileRef} type="file" className="text-sm text-muted" />
              {data.submission?.file?.url && <p className="mt-1 text-xs text-muted">Current: <a href={data.submission.file.url} className="text-trust hover:underline">{data.submission.file.name}</a></p>}
            </div>
          )}
          {error && <p className="mt-2 text-sm text-warn">{error}</p>}
          <button onClick={submit} disabled={busy} className="mt-3 rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
            {busy ? "Submitting…" : submitted ? "Resubmit" : "Submit"}
          </button>
        </div>
      )}
    </div>
  );
}

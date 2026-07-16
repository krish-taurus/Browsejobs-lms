"use client";

import Link from "next/link";
import dynamic from "next/dynamic";
import { useRouter } from "next/navigation";
import { use, useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";
import { type TutorConversation, isBudgetError } from "@/lib/tutor";

const MonacoEditor = dynamic(() => import("@monaco-editor/react"), { ssr: false });

type Lab = {
  lesson_id: number;
  title: string | null;
  language: string;
  monaco: string;
  instructions: string | null;
  starter_code: string | null;
  test_count: number;
};

type Result = {
  kind: string;
  status: string | null;
  stdout: string | null;
  stderr: string | null;
  passed_tests: number;
  total_tests: number;
  run_time_ms: number;
};

export default function LabPage({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const router = useRouter();
  const [lab, setLab] = useState<Lab | null>(null);
  const [code, setCode] = useState("");
  const [result, setResult] = useState<Result | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<"run" | "submit" | null>(null);
  const [tutorQ, setTutorQ] = useState("");
  const [tutorBusy, setTutorBusy] = useState(false);
  const [tutorErr, setTutorErr] = useState<string | null>(null);

  const load = useCallback(() => {
    apiJson<{ data: Lab }>(`/api/v1/me/labs/${lesson}`)
      .then((r) => { setLab(r.data); setCode(r.data.starter_code ?? ""); })
      .catch(() => setLab(null));
  }, [lesson]);

  useEffect(() => { load(); }, [load]);

  async function exec(kind: "run" | "submit") {
    setError(null);
    setBusy(kind);
    try {
      const r = await apiJson<{ data: Result }>(`/api/v1/me/labs/${lesson}/${kind}`, { method: "POST", body: JSON.stringify({ source: code }) });
      setResult(r.data);
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setBusy(null);
    }
  }

  async function askTutor() {
    if (!tutorQ.trim()) return;
    setTutorErr(null);
    setTutorBusy(true);
    try {
      const r = await apiJson<{ data: TutorConversation }>(`/api/v1/me/tutor/labs/${lesson}`, {
        method: "POST",
        body: JSON.stringify({ question: tutorQ }),
      });
      router.push(`/tutor/${r.data.id}`);
    } catch (err) {
      if (isBudgetError(err)) setTutorErr("You've reached today's AI tutor limit. It resets tomorrow.");
      else setTutorErr(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
      setTutorBusy(false);
    }
  }

  if (!lab) return <div className="mx-auto max-w-4xl"><div className="shimmer h-96 rounded-[14px]" /></div>;

  return (
    <div className="mx-auto max-w-4xl">
      <Link href="/labs" className="text-sm text-trust hover:underline">← Coding labs</Link>
      <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
        <h1 className="display text-2xl text-ink">{lab.title ?? "Coding lab"}</h1>
        <span className="mono rounded-full bg-sky px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-deep">{lab.language}</span>
      </div>

      {lab.instructions && (
        <p className="mt-3 rounded-[10px] border-l-2 border-trust bg-sky/40 px-3 py-2 text-sm text-ink">{lab.instructions}</p>
      )}

      <div className="mt-4 overflow-hidden rounded-[14px] border border-line">
        <MonacoEditor
          height="360px"
          language={lab.monaco}
          theme="vs-dark"
          value={code}
          onChange={(v) => setCode(v ?? "")}
          options={{ minimap: { enabled: false }, fontSize: 13, scrollBeyondLastLine: false }}
        />
      </div>

      <div className="mt-3 flex items-center gap-3">
        <button onClick={() => exec("run")} disabled={busy !== null} className="rounded-full border border-line px-5 py-2 text-sm font-semibold text-ink hover:bg-paper disabled:opacity-50">
          {busy === "run" ? "Running…" : "Run"}
        </button>
        <button onClick={() => exec("submit")} disabled={busy !== null} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white hover:bg-deep disabled:opacity-50">
          {busy === "submit" ? "Submitting…" : `Submit (${lab.test_count} tests)`}
        </button>
      </div>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      {result && (
        <div className="mt-4 rounded-[14px] border border-line bg-white p-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <span className="mono text-xs uppercase tracking-widest text-muted">{result.status ?? "done"} · {result.run_time_ms}ms</span>
            {result.total_tests > 0 && (
              <span className={`mono rounded-full px-2.5 py-0.5 text-[10px] uppercase tracking-widest ${result.passed_tests === result.total_tests ? "bg-verify-bg text-verify" : "bg-warn/10 text-warn"}`}>
                {result.passed_tests}/{result.total_tests} passed
              </span>
            )}
          </div>
          {result.stdout && <pre className="mono mt-2 max-h-48 overflow-auto rounded-[10px] bg-paper p-3 text-xs text-ink">{result.stdout}</pre>}
          {result.stderr && <pre className="mono mt-2 max-h-48 overflow-auto rounded-[10px] bg-warn/10 p-3 text-xs text-warn">{result.stderr}</pre>}
        </div>
      )}

      {/* Stuck? The tutor gives a hint (never the full solution) using your last run. */}
      <div className="mt-6 rounded-[14px] border border-line bg-white p-4">
        <p className="kicker text-trust">Stuck? Ask the tutor</p>
        <p className="mt-1 text-xs text-muted">You&apos;ll get a hint, not the answer — the tutor sees your latest run.</p>
        <textarea
          value={tutorQ}
          onChange={(e) => setTutorQ(e.target.value)}
          rows={2}
          placeholder="e.g. Why is my loop not printing anything?"
          className="mt-3 w-full resize-none rounded-[10px] border border-line bg-paper px-3 py-2 text-sm text-ink outline-none focus:border-trust"
        />
        {tutorErr && <p className="mt-2 text-sm text-warn">{tutorErr}</p>}
        <button
          onClick={askTutor}
          disabled={tutorBusy || !tutorQ.trim()}
          className="mt-2 rounded-full border border-line px-5 py-2 text-sm font-semibold text-trust hover:bg-sky disabled:opacity-50"
        >
          {tutorBusy ? "Thinking…" : "Ask for a hint"}
        </button>
      </div>
    </div>
  );
}

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

/** "main.py", "Main.java", "main.js" — the filename shown on the editor's tab. */
const FILENAMES: Record<string, string> = {
  python: "main.py",
  javascript: "main.js",
  typescript: "main.ts",
  java: "Main.java",
  cpp: "main.cpp",
  c: "main.c",
  csharp: "Main.cs",
  go: "main.go",
  ruby: "main.rb",
  php: "main.php",
  sql: "query.sql",
};

function PlayIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true" className="size-3.5" fill="currentColor">
      <path d="M5 3.4a.6.6 0 0 1 .92-.51l6.3 4.1a.6.6 0 0 1 0 1.02l-6.3 4.1A.6.6 0 0 1 5 12.6V3.4Z" />
    </svg>
  );
}

export default function LabPage({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const router = useRouter();
  const [lab, setLab] = useState<Lab | null>(null);
  const [code, setCode] = useState("");
  const [result, setResult] = useState<Result | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<"run" | "submit" | null>(null);
  // Labs that read input fail on Run with an empty stdin, which reads as a
  // broken program rather than a missing input. Give them somewhere to type it.
  const [stdin, setStdin] = useState("");
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
      const r = await apiJson<{ data: Result }>(`/api/v1/me/labs/${lesson}/${kind}`, { method: "POST", body: JSON.stringify(kind === "run" ? { source: code, stdin } : { source: code }) });
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

  if (!lab) return <div className="mx-auto max-w-6xl"><div className="shimmer h-96 rounded-[14px]" /></div>;

  const filename = FILENAMES[lab.language.toLowerCase()] ?? `main.${lab.monaco}`;

  return (
    <div className="mx-auto max-w-6xl">
      <Link href="/labs" className="text-sm text-trust hover:underline">← Coding labs</Link>
      <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
        <h1 className="display text-2xl text-ink">{lab.title ?? "Coding lab"}</h1>
        <span className="mono rounded-full bg-sky px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-deep">{lab.language}</span>
      </div>

      {lab.instructions && (
        <p className="mt-3 rounded-[10px] border-l-2 border-trust bg-sky/40 px-3 py-2 text-sm text-ink">{lab.instructions}</p>
      )}

      {/* Editor (left) + Output (right) side by side, like a real IDE. */}
      <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start">
        {/* ---------------------------------------------------------- Code */}
        <div className="overflow-hidden rounded-[14px] border border-line shadow-[0_1px_2px_rgba(16,24,40,0.03)]">
          {/* The "tab bar" — filename on the left, Run/Submit right where a
              real editor's toolbar lives, not buried below a stdin box. */}
          <div className="flex items-center justify-between gap-3 bg-[#1e1e1e] px-3 py-2">
            <span className="mono flex items-center gap-2 rounded-t-md bg-[#2a2a2a] px-3 py-1.5 text-xs text-white/80">
              <span className="size-1.5 rounded-full bg-verify" />
              {filename}
            </span>
            <div className="flex items-center gap-2">
              <button
                onClick={() => exec("run")}
                disabled={busy !== null}
                className="flex items-center gap-1.5 rounded-md bg-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/15 disabled:opacity-50"
              >
                <PlayIcon />{busy === "run" ? "Running…" : "Run"}
              </button>
              <button
                onClick={() => exec("submit")}
                disabled={busy !== null}
                className="rounded-md bg-trust px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-deep disabled:opacity-50"
              >
                {busy === "submit" ? "Submitting…" : `Submit (${lab.test_count})`}
              </button>
            </div>
          </div>

          <MonacoEditor
            height="360px"
            language={lab.monaco}
            theme="vs-dark"
            value={code}
            onChange={(v) => setCode(v ?? "")}
            options={{ minimap: { enabled: false }, fontSize: 13, scrollBeyondLastLine: false }}
          />

          <label className="block border-t border-white/10 bg-[#1e1e1e] px-3 py-2.5">
            <span className="mono text-[10px] uppercase tracking-widest text-white/40">Input (stdin) — one value per line</span>
            <textarea
              value={stdin}
              onChange={(e) => setStdin(e.target.value)}
              rows={2}
              spellCheck={false}
              placeholder={"3\n4"}
              className="mono mt-1.5 w-full resize-none rounded-md border border-white/10 bg-black/30 px-2.5 py-1.5 text-xs text-white/90 outline-none placeholder:text-white/25 focus:border-trust"
            />
            <span className="mt-1 block text-[10px] text-white/30">Only used by Run. Submit feeds each test its own input.</span>
          </label>
        </div>

        {/* -------------------------------------------------------- Output */}
        <div className="flex h-full flex-col overflow-hidden rounded-[14px] border border-line bg-[#1e1e1e] shadow-[0_1px_2px_rgba(16,24,40,0.03)]">
          <div className="mono flex items-center justify-between gap-2 border-b border-white/10 px-3 py-2 text-xs text-white/80">
            <span>Output</span>
            {result && (
              <span className="flex items-center gap-2">
                <span className="text-white/40">{result.status ?? "done"} · {result.run_time_ms}ms</span>
                {result.total_tests > 0 && (
                  <span className={`rounded-full px-2 py-0.5 text-[10px] uppercase tracking-widest ${result.passed_tests === result.total_tests ? "bg-verify/20 text-verify" : "bg-warn/20 text-warn"}`}>
                    {result.passed_tests}/{result.total_tests} passed
                  </span>
                )}
              </span>
            )}
          </div>

          <div className="min-h-[240px] flex-1 overflow-auto p-3">
            {error && <p className="mono text-xs text-warn">{error}</p>}
            {!error && !result && (
              <p className="mono text-xs text-white/30">Run your code to see the output here.</p>
            )}
            {result?.stdout && <pre className="mono whitespace-pre-wrap text-xs text-white/90">{result.stdout}</pre>}
            {result?.stderr && (
              <pre className="mono mt-2 whitespace-pre-wrap rounded-md bg-warn/10 p-2 text-xs text-warn">{result.stderr}</pre>
            )}
            {result && !result.stdout && !result.stderr && (
              <p className="mono text-xs text-white/30">(no output)</p>
            )}
          </div>
        </div>
      </div>

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

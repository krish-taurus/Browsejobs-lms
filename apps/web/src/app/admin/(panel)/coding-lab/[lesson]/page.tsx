"use client";

import Link from "next/link";
import { use, useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

/**
 * Coding-lab authoring (admins with manage-curriculum). Write the problem,
 * starter code and the hidden test cases the student's submission is checked
 * against. The student solves it inline at /labs; execution runs via Judge0.
 */

type TestCase = { stdin: string; expected_stdout: string };
type Lab = {
  language: string;
  instructions: string | null;
  starter_code: string | null;
  test_cases: TestCase[];
  time_limit_ms: number | null;
};

const LANGUAGES = ["python", "sql", "bash", "javascript"];
const inputCls = "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";
const codeCls = "mono w-full rounded-[10px] border border-line bg-paper px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function CodingLabEditor({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const [language, setLanguage] = useState("python");
  const [instructions, setInstructions] = useState("");
  const [starter, setStarter] = useState("");
  const [timeLimit, setTimeLimit] = useState<string>("");
  const [tests, setTests] = useState<TestCase[]>([{ stdin: "", expected_stdout: "" }]);

  const { data } = useQuery({
    queryKey: ["admin", "coding-lab", lesson],
    queryFn: () => apiJson<{ data: Lab | null }>(`/api/v1/admin/lessons/${lesson}/coding-lab`),
  });

  useEffect(() => {
    const l = data?.data;
    if (l) {
      setLanguage(l.language);
      setInstructions(l.instructions ?? "");
      setStarter(l.starter_code ?? "");
      setTimeLimit(l.time_limit_ms ? String(l.time_limit_ms) : "");
      setTests(l.test_cases.length ? l.test_cases : [{ stdin: "", expected_stdout: "" }]);
    }
  }, [data]);

  const save = useMutation({
    mutationFn: () =>
      apiJson(`/api/v1/admin/lessons/${lesson}/coding-lab`, {
        method: "PUT",
        body: JSON.stringify({
          language,
          instructions,
          starter_code: starter,
          test_cases: tests.filter((t) => t.expected_stdout.trim()),
          time_limit_ms: timeLimit ? Number(timeLimit) : null,
        }),
      }),
    onSuccess: () => {
      setError(null);
      setSaved(true);
      void qc.invalidateQueries({ queryKey: ["admin", "coding-lab", lesson] });
    },
    onError: (err) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Save failed."),
  });

  const validTests = tests.filter((t) => t.expected_stdout.trim()).length;

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/admin/curriculum" className="text-sm text-trust hover:underline">← Curriculum</Link>
      <h1 className="display mt-3 text-2xl text-ink">Coding lab</h1>
      <p className="mt-1 text-sm text-ink2/70">
        Write the problem and the hidden test cases. Students solve it inline; their code is run against every test.
      </p>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      <div className="mt-4 space-y-4 rounded-2xl border border-line bg-white p-5">
        <div>
          <label className="text-xs font-semibold text-muted">Language</label>
          <select className={`${inputCls} mt-1`} value={language} onChange={(e) => setLanguage(e.target.value)}>
            {LANGUAGES.map((l) => <option key={l} value={l}>{l}</option>)}
          </select>
        </div>

        <div>
          <label className="text-xs font-semibold text-muted">Problem statement</label>
          <textarea className={`${inputCls} mt-1`} rows={5} value={instructions}
            onChange={(e) => setInstructions(e.target.value)}
            placeholder="Describe the task. What should the student's program read and print?" />
        </div>

        <div>
          <label className="text-xs font-semibold text-muted">Starter code (optional)</label>
          <textarea className={`${codeCls} mt-1`} rows={5} value={starter}
            onChange={(e) => setStarter(e.target.value)} placeholder="# what the student starts with" />
        </div>

        <div>
          <div className="flex items-center justify-between">
            <label className="text-xs font-semibold text-muted">Test cases (hidden from students)</label>
            <span className="mono text-[10px] text-muted">{validTests} active</span>
          </div>
          <div className="mt-2 space-y-2">
            {tests.map((t, i) => (
              <div key={i} className="grid gap-2 rounded-[10px] border border-line p-2.5 md:grid-cols-2">
                <div>
                  <span className="mono text-[10px] uppercase tracking-widest text-muted">stdin (input)</span>
                  <textarea className={`${codeCls} mt-1`} rows={2} value={t.stdin}
                    onChange={(e) => setTests(tests.map((x, j) => j === i ? { ...x, stdin: e.target.value } : x))} />
                </div>
                <div>
                  <span className="mono text-[10px] uppercase tracking-widest text-muted">expected stdout</span>
                  <textarea className={`${codeCls} mt-1`} rows={2} value={t.expected_stdout}
                    onChange={(e) => setTests(tests.map((x, j) => j === i ? { ...x, expected_stdout: e.target.value } : x))} />
                </div>
                <button onClick={() => setTests(tests.filter((_, j) => j !== i))}
                  className="justify-self-start text-xs font-semibold text-warn hover:underline md:col-span-2">
                  Remove test
                </button>
              </div>
            ))}
          </div>
          <button onClick={() => setTests([...tests, { stdin: "", expected_stdout: "" }])}
            className="mt-2 text-xs font-semibold text-trust hover:underline">+ Add test case</button>
        </div>

        <div>
          <label className="text-xs font-semibold text-muted">Time limit (ms, optional)</label>
          <input type="number" className={`${inputCls} mt-1`} value={timeLimit}
            onChange={(e) => setTimeLimit(e.target.value)} placeholder="e.g. 5000" min={500} max={30000} />
        </div>

        <div className="flex items-center gap-3">
          <button onClick={() => { setSaved(false); save.mutate(); }}
            disabled={save.isPending || validTests === 0}
            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50">
            {save.isPending ? "Saving…" : "Save lab"}
          </button>
          {validTests === 0 && <span className="text-xs text-muted">Add at least one test case with expected output.</span>}
          {saved && <span className="mono text-xs text-verify">Saved ✓</span>}
        </div>
      </div>
    </div>
  );
}

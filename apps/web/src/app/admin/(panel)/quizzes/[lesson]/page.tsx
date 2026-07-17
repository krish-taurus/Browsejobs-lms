"use client";

import Link from "next/link";
import { use, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type Question = { id: number; prompt: string; options: string[]; correct_index: number; explanation: string | null };
type Quiz = {
  id: number;
  lesson_id: number;
  title: string;
  time_limit_sec: number;
  pass_pct: number;
  status: "draft" | "approved";
  source: "ai" | "manual";
  dispatchable: boolean;
  questions: Question[];
};

const inputCls = "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";
const empty = { prompt: "", options: ["", "", "", ""], correct_index: 0, explanation: "" };

export default function QuizManagerPage({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState(empty);

  const quizQuery = useQuery({
    queryKey: ["admin", "quiz", lesson],
    queryFn: () => apiJson<{ data: Quiz | null }>(`/api/v1/admin/lessons/${lesson}/quiz`),
  });
  const quiz = quizQuery.data?.data ?? null;

  const refresh = () => void qc.invalidateQueries({ queryKey: ["admin", "quiz", lesson] });
  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const createQuiz = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/lessons/${lesson}/quiz`, { method: "PUT", body: JSON.stringify({ title: "Module quiz" }) }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });
  const generate = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/lessons/${lesson}/quiz/generate`, { method: "POST", body: JSON.stringify({ count: 8 }) }),
    onSuccess: () => { setError(null); setTimeout(refresh, 1500); },
    onError,
  });
  const addQuestion = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/quizzes/${quiz!.id}/questions`, {
      method: "POST",
      body: JSON.stringify({ prompt: form.prompt, options: form.options.filter((o) => o.trim()), correct_index: form.correct_index }),
    }),
    onSuccess: () => { setForm(empty); setError(null); refresh(); },
    onError,
  });
  const removeQuestion = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/quiz-questions/${id}`, { method: "DELETE" }),
    onSuccess: refresh,
    onError,
  });
  const approve = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/quizzes/${quiz!.id}/approve`, { method: "POST", body: JSON.stringify({}) }),
    onSuccess: () => { setError(null); refresh(); },
    onError,
  });

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/admin/quizzes" className="text-sm text-trust hover:underline">← Module quizzes</Link>

      {quizQuery.isLoading ? (
        <div className="mt-4 shimmer h-64 rounded-[14px]" />
      ) : quiz === null ? (
        <div className="mt-6 rounded-2xl border border-line bg-white p-6">
          <h1 className="display text-2xl text-ink">No quiz yet</h1>
          <p className="mt-1 text-sm text-muted">Create a quiz for this lesson, then generate questions with AI or add them by hand.</p>
          <button onClick={() => createQuiz.mutate()} disabled={createQuiz.isPending} className="mt-4 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
            {createQuiz.isPending ? "Creating…" : "Create quiz"}
          </button>
        </div>
      ) : (
        <>
          <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div>
              <h1 className="display text-2xl text-ink">{quiz.title}</h1>
              <p className="mono text-[11px] uppercase tracking-widest text-muted">
                {quiz.status} · {quiz.source} · {quiz.questions.length} questions · {Math.round(quiz.time_limit_sec / 60)} min · pass {quiz.pass_pct}%
              </p>
            </div>
            <div className="flex gap-2">
              <button onClick={() => generate.mutate()} disabled={generate.isPending} className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-paper disabled:opacity-50">
                {generate.isPending ? "Generating…" : "Generate with AI"}
              </button>
              <button onClick={() => approve.mutate()} disabled={approve.isPending || quiz.status === "approved" || quiz.questions.length === 0} className="rounded-full bg-verify px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                {quiz.status === "approved" ? "Approved ✓" : "Approve"}
              </button>
            </div>
          </div>

          {error && <p className="mt-3 text-sm text-warn">{error}</p>}

          <div className="mt-6 space-y-3">
            {quiz.questions.map((q, i) => (
              <div key={q.id} className="rounded-[14px] border border-line bg-white p-4">
                <div className="flex items-start justify-between gap-3">
                  <p className="text-sm font-semibold text-ink">{i + 1}. {q.prompt}</p>
                  <button onClick={() => removeQuestion.mutate(q.id)} className="text-xs font-semibold text-warn hover:underline">Delete</button>
                </div>
                <ul className="mt-2 space-y-1">
                  {q.options.map((opt, oi) => (
                    <li key={oi} className={`text-sm ${oi === q.correct_index ? "font-semibold text-verify" : "text-muted"}`}>
                      {oi === q.correct_index ? "✓ " : "• "}{opt}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>

          {/* Add a question by hand */}
          <div className="mt-6 rounded-2xl border border-line bg-white p-5">
            <p className="text-sm font-semibold text-ink">Add a question</p>
            <input className={`${inputCls} mt-3`} placeholder="Question prompt" value={form.prompt} onChange={(e) => setForm({ ...form, prompt: e.target.value })} />
            <div className="mt-3 space-y-2">
              {form.options.map((opt, oi) => (
                <label key={oi} className="flex items-center gap-2">
                  <input type="radio" name="correct" checked={form.correct_index === oi} onChange={() => setForm({ ...form, correct_index: oi })} className="accent-trust" />
                  <input className={inputCls} placeholder={`Option ${oi + 1}`} value={opt} onChange={(e) => { const o = [...form.options]; o[oi] = e.target.value; setForm({ ...form, options: o }); }} />
                </label>
              ))}
            </div>
            <button
              onClick={() => addQuestion.mutate()}
              disabled={addQuestion.isPending || !form.prompt.trim() || form.options.filter((o) => o.trim()).length < 2}
              className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              Add question
            </button>
            <p className="mt-2 text-xs text-muted">Select the radio next to the correct option. Editing questions reverts the quiz to draft.</p>
          </div>
        </>
      )}
    </div>
  );
}

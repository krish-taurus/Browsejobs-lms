"use client";

import Link from "next/link";
import { use, useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type Criterion = { criterion: string; max_points: number };
type Assignment = {
  lesson_id: number;
  title: string;
  instructions: string | null;
  rubric: Criterion[];
  max_points: number;
  allow_link: boolean;
  allow_file: boolean;
};

const inputCls = "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function AssignmentBuilderPage({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [title, setTitle] = useState("Assignment");
  const [instructions, setInstructions] = useState("");
  const [rubric, setRubric] = useState<Criterion[]>([{ criterion: "", max_points: 50 }]);

  const query = useQuery({
    queryKey: ["admin", "assignment", lesson],
    queryFn: () => apiJson<{ data: Assignment | null }>(`/api/v1/admin/lessons/${lesson}/assignment`),
  });

  useEffect(() => {
    const a = query.data?.data;
    if (a) { setTitle(a.title); setInstructions(a.instructions ?? ""); setRubric(a.rubric.length ? a.rubric : [{ criterion: "", max_points: 50 }]); }
  }, [query.data]);

  const save = useMutation({
    mutationFn: () => apiJson(`/api/v1/admin/lessons/${lesson}/assignment`, {
      method: "PUT",
      body: JSON.stringify({ title, instructions, rubric: rubric.filter((r) => r.criterion.trim()) }),
    }),
    onSuccess: () => { setError(null); void qc.invalidateQueries({ queryKey: ["admin", "assignment", lesson] }); },
    onError: (err) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Save failed."),
  });

  const generate = useMutation({
    mutationFn: () =>
      apiJson<{ data: { title: string; instructions: string; rubric: Criterion[] } }>(
        `/api/v1/admin/lessons/${lesson}/assignment/generate`,
        { method: "POST" },
      ),
    onSuccess: (res) => {
      setError(null);
      setTitle(res.data.title);
      setInstructions(res.data.instructions);
      setRubric(res.data.rubric.length ? res.data.rubric : [{ criterion: "", max_points: 50 }]);
    },
    onError: (err) =>
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not generate. Try again."),
  });

  const total = rubric.reduce((s, r) => s + (Number(r.max_points) || 0), 0);

  return (
    <div className="mx-auto max-w-2xl">
      <Link href="/admin/assignments" className="text-sm text-trust hover:underline">← Assignments</Link>
      <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
        <h1 className="display text-2xl text-ink">Assignment rubric</h1>
        <button
          onClick={() => generate.mutate()}
          disabled={generate.isPending}
          className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink transition-colors hover:border-trust disabled:opacity-50"
        >
          {generate.isPending ? "Generating…" : "✨ Generate with AI"}
        </button>
      </div>
      <p className="mt-1 text-sm text-ink2/70">
        Generate a draft from this lesson&apos;s topics, then edit and save — or write it by hand.
      </p>

      <div className="mt-4 rounded-2xl border border-line bg-white p-5">
        <label className="text-xs font-semibold text-muted">Title</label>
        <input className={`${inputCls} mt-1`} value={title} onChange={(e) => setTitle(e.target.value)} />
        <label className="mt-3 block text-xs font-semibold text-muted">Instructions</label>
        <textarea className={`${inputCls} mt-1`} rows={3} value={instructions} onChange={(e) => setInstructions(e.target.value)} />

        <div className="mt-4 flex items-center justify-between">
          <p className="text-sm font-semibold text-ink">Rubric criteria</p>
          <span className="mono text-xs text-muted">total {total} pts</span>
        </div>
        <div className="mt-2 space-y-2">
          {rubric.map((r, i) => (
            <div key={i} className="flex items-center gap-2">
              <input className={inputCls} placeholder="Criterion" value={r.criterion} onChange={(e) => setRubric(rubric.map((x, j) => j === i ? { ...x, criterion: e.target.value } : x))} />
              <input type="number" className="w-24 rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust" value={r.max_points} onChange={(e) => setRubric(rubric.map((x, j) => j === i ? { ...x, max_points: Number(e.target.value) } : x))} />
              <button onClick={() => setRubric(rubric.filter((_, j) => j !== i))} className="text-xs font-semibold text-warn hover:underline">✕</button>
            </div>
          ))}
        </div>
        <button onClick={() => setRubric([...rubric, { criterion: "", max_points: 20 }])} className="mt-2 text-xs font-semibold text-trust hover:underline">+ Add criterion</button>

        {error && <p className="mt-3 text-sm text-warn">{error}</p>}
        <button onClick={() => save.mutate()} disabled={save.isPending || !title.trim() || rubric.filter((r) => r.criterion.trim()).length === 0} className="mt-4 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {save.isPending ? "Saving…" : "Save rubric"}
        </button>
      </div>
    </div>
  );
}

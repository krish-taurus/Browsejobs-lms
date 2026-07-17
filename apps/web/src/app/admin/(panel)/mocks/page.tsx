"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Blueprint = {
  id: number;
  course_id: number;
  role_title: string;
  competencies: string[];
  opening_question: string;
  is_active: boolean;
  course?: { id: number; name: string } | null;
};

type RecentMock = {
  id: number;
  student: string | null;
  role_title: string | null;
  overall_score: number | null;
  scorecard_source: string | null;
  completed_at: string | null;
};

type Course = { id: number; name: string };

type Form = {
  course_id: string;
  role_title: string;
  competencies: string; // comma-separated in the form
  opening_question: string;
};

const emptyForm: Form = { course_id: "", role_title: "", competencies: "", opening_question: "" };

export default function AdminMocksPage() {
  const qc = useQueryClient();
  const [form, setForm] = useState<Form>(emptyForm);
  const [editing, setEditing] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "mock-blueprints"],
    queryFn: () => apiJson<{ data: { blueprints: Blueprint[]; recent_mocks: RecentMock[] } }>("/api/v1/admin/mock-blueprints"),
  });
  const { data: coursesData } = useQuery({
    queryKey: ["admin", "courses"],
    queryFn: () => apiJson<{ data: Course[] }>("/api/v1/admin/courses"),
  });

  const blueprints = data?.data.blueprints ?? [];
  const recent = data?.data.recent_mocks ?? [];
  const courses = coursesData?.data ?? [];

  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "mock-blueprints"] });
  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");

  const payload = () => ({
    course_id: Number(form.course_id),
    role_title: form.role_title,
    competencies: form.competencies.split(",").map((c) => c.trim()).filter(Boolean),
    opening_question: form.opening_question,
  });

  const save = useMutation({
    mutationFn: () =>
      editing
        ? apiJson(`/api/v1/admin/mock-blueprints/${editing}`, { method: "PATCH", body: JSON.stringify(payload()) })
        : apiJson("/api/v1/admin/mock-blueprints", { method: "POST", body: JSON.stringify(payload()) }),
    onSuccess: () => { setForm(emptyForm); setEditing(null); setError(null); refresh(); },
    onError,
  });

  const toggle = useMutation({
    mutationFn: (b: Blueprint) =>
      apiJson(`/api/v1/admin/mock-blueprints/${b.id}`, { method: "PATCH", body: JSON.stringify({ is_active: !b.is_active }) }),
    onSuccess: refresh,
    onError,
  });

  const remove = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/mock-blueprints/${id}`, { method: "DELETE" }),
    onSuccess: refresh,
    onError,
  });

  function startEdit(b: Blueprint) {
    setEditing(b.id);
    setForm({
      course_id: String(b.course_id),
      role_title: b.role_title,
      competencies: b.competencies.join(", "),
      opening_question: b.opening_question,
    });
  }

  const canSave = form.role_title.trim() && form.opening_question.trim() && form.competencies.trim() && (editing || form.course_id);

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">AI mock interviewer</p>
      <h1 className="display mt-2 text-3xl text-ink">Interview blueprints</h1>
      <p className="mt-1 text-sm text-muted">
        One blueprint per course: the target role, the competencies on the scorecard, and the opening
        question. Students practice against these; enable the feature itself under Monetization.
      </p>

      <div className="mt-8 rounded-[14px] border border-line bg-white p-5">
        <h2 className="text-sm font-semibold text-ink">{editing ? "Edit blueprint" : "New blueprint"}</h2>
        {error && <p className="mt-2 text-sm text-warn">{error}</p>}
        <div className="mt-3 grid gap-3 sm:grid-cols-2">
          <select
            value={form.course_id}
            onChange={(e) => setForm({ ...form, course_id: e.target.value })}
            disabled={editing !== null}
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust disabled:opacity-50"
          >
            <option value="">Course…</option>
            {courses.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
          <input
            value={form.role_title}
            onChange={(e) => setForm({ ...form, role_title: e.target.value })}
            placeholder="Role title (e.g. Data Engineer)"
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          />
        </div>
        <input
          value={form.competencies}
          onChange={(e) => setForm({ ...form, competencies: e.target.value })}
          placeholder="Competencies, comma-separated (e.g. SQL, Python, Communication)"
          className="mt-3 w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
        />
        <textarea
          value={form.opening_question}
          onChange={(e) => setForm({ ...form, opening_question: e.target.value })}
          rows={2}
          placeholder="Opening question the interviewer always starts with"
          className="mt-3 w-full resize-none rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
        />
        <div className="mt-3 flex gap-2">
          <button
            onClick={() => save.mutate()}
            disabled={!canSave || save.isPending}
            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
          >
            {save.isPending ? "Saving…" : editing ? "Save changes" : "Create blueprint"}
          </button>
          {editing && (
            <button
              onClick={() => { setEditing(null); setForm(emptyForm); }}
              className="rounded-full border border-line bg-white px-5 py-2 text-sm text-ink"
            >
              Cancel
            </button>
          )}
        </div>
      </div>

      {isLoading ? (
        <div className="mt-8 space-y-2">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : blueprints.length === 0 ? (
        <div className="mt-8"><EmptyState title="No blueprints yet" body="Create one per course so students can start text mocks." /></div>
      ) : (
        <div className="mt-8 divide-y divide-line rounded-[14px] border border-line bg-white">
          {blueprints.map((b) => (
            <div key={b.id} className="flex items-center gap-3 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-ink">{b.role_title}</p>
                <p className="mono truncate text-[11px] uppercase tracking-widest text-muted">
                  {b.course?.name ?? "—"} · {b.competencies.join(" · ")}
                </p>
              </div>
              <span className={`mono rounded-full px-2.5 py-0.5 text-[11px] uppercase tracking-widest ${b.is_active ? "bg-verify-bg text-verify" : "bg-paper text-muted"}`}>
                {b.is_active ? "active" : "off"}
              </span>
              <button onClick={() => startEdit(b)} className="text-xs text-trust hover:underline">Edit</button>
              <button onClick={() => toggle.mutate(b)} className="text-xs text-muted hover:underline">
                {b.is_active ? "Disable" : "Enable"}
              </button>
              <button onClick={() => remove.mutate(b.id)} className="text-xs text-warn hover:underline">Delete</button>
            </div>
          ))}
        </div>
      )}

      <h2 className="mt-10 text-sm font-semibold uppercase tracking-widest text-muted">Recent completed mocks</h2>
      {recent.length === 0 ? (
        <p className="mt-3 text-sm text-muted">No completed mocks yet.</p>
      ) : (
        <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
          {recent.map((m) => (
            <div key={m.id} className="flex items-center gap-3 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-ink">{m.student ?? "Student"}</p>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">
                  {m.role_title ?? "—"} · {m.completed_at ? new Date(m.completed_at).toLocaleDateString("en-IN") : "—"}
                  {m.scorecard_source === "fallback" && " · fallback card"}
                </p>
              </div>
              <span className="text-sm font-semibold text-ink">{m.overall_score ?? "—"}/100</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

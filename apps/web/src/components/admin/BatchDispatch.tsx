"use client";

import { useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type MockOption = { id: number; skill: string | null; role_title: string };
type AssignmentOption = { lesson_id: number; title: string | null };

const inputCls =
  "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export function BatchDispatch({ batchId }: { batchId: string }) {
  const [mockId, setMockId] = useState("");
  const [lessonId, setLessonId] = useState("");
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const { data } = useQuery({
    queryKey: ["admin", "batch-dispatch-options", batchId],
    queryFn: () => apiJson<{ mocks: MockOption[]; assignments: AssignmentOption[] }>(`/api/v1/admin/batches/${batchId}/dispatch-options`),
  });

  const onError = (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not send.");

  const sendMock = useMutation({
    mutationFn: () => apiJson<{ data: { notified: number } }>(`/api/v1/admin/batches/${batchId}/dispatch-mock`, {
      method: "POST", body: JSON.stringify({ blueprint_id: Number(mockId) }),
    }),
    onSuccess: (r) => { setError(null); setMockId(""); setMsg(`Mock sent to ${r.data.notified} student(s).`); },
    onError,
  });

  const sendAssignment = useMutation({
    mutationFn: () => apiJson<{ data: { notified: number } }>(`/api/v1/admin/batches/${batchId}/dispatch-assignment`, {
      method: "POST", body: JSON.stringify({ lesson_id: Number(lessonId) }),
    }),
    onSuccess: (r) => { setError(null); setLessonId(""); setMsg(`Assignment sent to ${r.data.notified} student(s).`); },
    onError,
  });

  const mocks = data?.mocks ?? [];
  const assignments = data?.assignments ?? [];

  return (
    <div className="mt-10">
      <h2 className="display text-xl text-ink">Send to this batch</h2>
      <p className="mt-1 text-sm text-muted">Push a specific practice mock or assignment to everyone in the batch after a class.</p>

      {error && <p className="mt-3 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}
      {msg && <p className="mt-3 rounded-[10px] bg-verify-bg px-3 py-2 text-sm text-ink">{msg}</p>}

      <div className="mt-4 grid gap-4 sm:grid-cols-2">
        <div className="rounded-[14px] border border-line bg-white p-5">
          <p className="kicker text-muted">Practice mock</p>
          <select value={mockId} onChange={(e) => setMockId(e.target.value)} className={`${inputCls} mt-3 w-full`}>
            <option value="">Choose a mock…</option>
            {mocks.map((m) => <option key={m.id} value={m.id}>{m.skill ? `${m.skill} — ` : ""}{m.role_title}</option>)}
          </select>
          <button
            onClick={() => sendMock.mutate()}
            disabled={!mockId || sendMock.isPending}
            className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
          >
            {sendMock.isPending ? "Sending…" : "Send mock"}
          </button>
          {mocks.length === 0 && <p className="mt-2 text-xs text-muted">No mocks for this course yet — add one under Mocks.</p>}
        </div>

        <div className="rounded-[14px] border border-line bg-white p-5">
          <p className="kicker text-muted">Assignment</p>
          <select value={lessonId} onChange={(e) => setLessonId(e.target.value)} className={`${inputCls} mt-3 w-full`}>
            <option value="">Choose an assignment…</option>
            {assignments.map((a) => <option key={a.lesson_id} value={a.lesson_id}>{a.title}</option>)}
          </select>
          <button
            onClick={() => sendAssignment.mutate()}
            disabled={!lessonId || sendAssignment.isPending}
            className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
          >
            {sendAssignment.isPending ? "Sending…" : "Send assignment"}
          </button>
          {assignments.length === 0 && <p className="mt-2 text-xs text-muted">No assignments for this course yet.</p>}
        </div>
      </div>
    </div>
  );
}

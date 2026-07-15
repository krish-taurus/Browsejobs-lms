"use client";

import Link from "next/link";
import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type BatchRow = {
  id: number;
  number: string;
  type: string;
  capacity: number | null;
  starts_on: string | null;
  members_count: number;
  course: { id: number; code: string; name: string };
};

type CourseRow = { id: number; code: string; name: string };

export default function AdminBatchesPage() {
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ course_id: "", type: "bootcamp", capacity: "", number: "", starts_on: "" });

  const { data: batches, isLoading } = useQuery({
    queryKey: ["admin", "batches"],
    queryFn: () => apiJson<{ data: BatchRow[] }>("/api/v1/admin/batches"),
  });

  const { data: courses } = useQuery({
    queryKey: ["admin", "courses"],
    queryFn: () => apiJson<{ data: CourseRow[] }>("/api/v1/admin/courses"),
  });

  const create = useMutation({
    mutationFn: () =>
      apiJson("/api/v1/admin/batches", {
        method: "POST",
        body: JSON.stringify({
          course_id: Number(form.course_id),
          type: form.type,
          capacity: form.capacity ? Number(form.capacity) : null,
          number: form.number || null,
          starts_on: form.starts_on || null,
        }),
      }),
    onSuccess: () => {
      setForm({ course_id: "", type: "bootcamp", capacity: "", number: "", starts_on: "" });
      setError(null);
      void queryClient.invalidateQueries({ queryKey: ["admin", "batches"] });
    },
    onError: (err) =>
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not create batch."),
  });

  const inputCls =
    "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

  return (
    <div className="mx-auto max-w-5xl">
      <p className="kicker text-trust">Batches</p>
      <h1 className="display mt-2 text-3xl text-ink">Batch management</h1>

      {/* Create */}
      <div className="mt-8 rounded-[14px] border border-line bg-white p-5">
        <p className="kicker text-muted">Create a batch</p>
        {error && <p className="mt-3 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}
        <form
          onSubmit={(e) => {
            e.preventDefault();
            create.mutate();
          }}
          className="mt-4 flex flex-wrap items-end gap-3"
        >
          <label className="flex flex-col gap-1 text-xs text-muted">
            Course
            <select
              required
              value={form.course_id}
              onChange={(e) => setForm({ ...form, course_id: e.target.value })}
              className={inputCls}
            >
              <option value="">Select…</option>
              {courses?.data.map((c) => (
                <option key={c.id} value={c.id}>{c.code} — {c.name}</option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1 text-xs text-muted">
            Type
            <select
              value={form.type}
              onChange={(e) => setForm({ ...form, type: e.target.value })}
              className={inputCls}
            >
              <option value="masterclass">Masterclass</option>
              <option value="bootcamp">Bootcamp</option>
              <option value="paid">Paid</option>
            </select>
          </label>
          <label className="flex flex-col gap-1 text-xs text-muted">
            Capacity
            <input
              type="number"
              min={1}
              value={form.capacity}
              onChange={(e) => setForm({ ...form, capacity: e.target.value })}
              placeholder="∞"
              className={`${inputCls} w-24`}
            />
          </label>
          <label className="flex flex-col gap-1 text-xs text-muted">
            Starts
            <input
              type="date"
              value={form.starts_on}
              onChange={(e) => setForm({ ...form, starts_on: e.target.value })}
              className={inputCls}
            />
          </label>
          <label className="flex flex-col gap-1 text-xs text-muted">
            Number (blank = auto)
            <input
              value={form.number}
              onChange={(e) => setForm({ ...form, number: e.target.value })}
              placeholder="{COURSE}-{YYYYMM}-{seq}"
              className={`${inputCls} mono w-44`}
            />
          </label>
          <button
            disabled={create.isPending || !form.course_id}
            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
          >
            {create.isPending ? "Creating…" : "Create batch"}
          </button>
        </form>
      </div>

      {/* List */}
      {isLoading ? (
        <div className="mt-6 space-y-3">
          {Array.from({ length: 4 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}
        </div>
      ) : (
        <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
          {batches?.data.length === 0 && (
            <p className="px-5 py-8 text-center text-sm text-muted">
              No batches yet — create the first one above.
            </p>
          )}
          {batches?.data.map((b) => (
            <Link
              key={b.id}
              href={`/admin/batches/${b.id}`}
              className="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-paper"
            >
              <span className="mono font-semibold text-ink">{b.number}</span>
              <span className="mono rounded-full bg-sky px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-deep">
                {b.type}
              </span>
              <span className="flex-1 text-sm text-muted">{b.course.name}</span>
              <span className="mono text-sm text-muted">
                {b.members_count}{b.capacity ? ` / ${b.capacity}` : ""} students
              </span>
              <span className="text-trust">→</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}

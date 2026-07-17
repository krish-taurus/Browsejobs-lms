"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type MentorRow = {
  id: number;
  user_id: number;
  name: string | null;
  email: string | null;
  headline: string | null;
  expertise: string[];
  course_ids: number[];
  is_active: boolean;
  rating: number | null;
  booked: number;
  completed: number;
  no_shows: number;
};

type Course = { id: number; name: string };

type SessionRow = {
  id: number;
  mentor: string | null;
  student: string | null;
  purpose: string;
  status: string;
  no_show_side: string | null;
  starts_at: string;
  feedback_score: number | null;
  student_rating: number | null;
};

function ist(iso: string): string {
  return new Date(iso).toLocaleString("en-IN", {
    timeZone: "Asia/Kolkata", day: "numeric", month: "short", hour: "numeric", minute: "2-digit",
  });
}

export default function AdminMentorsPage() {
  const qc = useQueryClient();
  const [userId, setUserId] = useState("");
  const [headline, setHeadline] = useState("");
  const [expertise, setExpertise] = useState("");
  const [courseIds, setCourseIds] = useState<number[]>([]);
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "mentors"],
    queryFn: () => apiJson<{ data: { mentors: MentorRow[]; sessions: SessionRow[] } }>("/api/v1/admin/mentors"),
  });
  const { data: coursesData } = useQuery({
    queryKey: ["admin", "courses"],
    queryFn: () => apiJson<{ data: Course[] }>("/api/v1/admin/courses"),
  });

  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "mentors"] });
  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");

  const create = useMutation({
    mutationFn: () =>
      apiJson("/api/v1/admin/mentors", {
        method: "POST",
        body: JSON.stringify({
          user_id: Number(userId),
          headline: headline.trim() || null,
          expertise: expertise.split(",").map((t) => t.trim().toLowerCase()).filter(Boolean),
          course_ids: courseIds,
        }),
      }),
    onSuccess: () => { setUserId(""); setHeadline(""); setExpertise(""); setCourseIds([]); setError(null); refresh(); },
    onError,
  });

  const toggle = useMutation({
    mutationFn: (m: MentorRow) =>
      apiJson(`/api/v1/admin/mentors/${m.id}`, { method: "PATCH", body: JSON.stringify({ is_active: !m.is_active }) }),
    onSuccess: refresh,
    onError,
  });

  const mentors = data?.data.mentors ?? [];
  const sessions = data?.data.sessions ?? [];

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Mentor pool</p>
      <h1 className="display mt-2 text-3xl text-ink">Mentors</h1>
      <p className="mt-1 text-sm text-muted">
        Add staff as mentors and watch the session pipeline. Each mentor sets their own weekly hours
        on their Mentoring page; students book from the combined calendar.
      </p>

      <div className="mt-8 rounded-[14px] border border-line bg-white p-5">
        <h2 className="text-sm font-semibold text-ink">Add a mentor</h2>
        {error && <p className="mt-2 text-sm text-warn">{error}</p>}
        <div className="mt-3 grid gap-3 sm:grid-cols-3">
          <input value={userId} onChange={(e) => setUserId(e.target.value)} placeholder="Staff user ID"
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust" />
          <input value={headline} onChange={(e) => setHeadline(e.target.value)} placeholder="Headline (e.g. Senior DE · 8 yrs)"
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust" />
          <input value={expertise} onChange={(e) => setExpertise(e.target.value)} placeholder="Expertise, comma-separated"
            className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust" />
        </div>
        <p className="mt-3 text-xs font-semibold uppercase tracking-widest text-muted">Covers courses</p>
        <p className="text-xs text-muted">Students only see mentors for their own course. Leave all unticked for a generalist visible to everyone.</p>
        <div className="mt-2 flex flex-wrap gap-3">
          {(coursesData?.data ?? []).map((c) => (
            <label key={c.id} className="flex cursor-pointer items-center gap-1.5 text-xs text-ink">
              <input
                type="checkbox"
                checked={courseIds.includes(c.id)}
                onChange={(e) =>
                  setCourseIds(e.target.checked ? [...courseIds, c.id] : courseIds.filter((id) => id !== c.id))
                }
                className="accent-trust"
              />
              {c.name}
            </label>
          ))}
        </div>
        <button onClick={() => create.mutate()} disabled={create.isPending || !userId || !expertise.trim()}
          className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {create.isPending ? "Saving…" : "Add mentor"}
        </button>
      </div>

      {isLoading ? (
        <div className="mt-8 space-y-2">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : mentors.length === 0 ? (
        <div className="mt-8"><EmptyState title="No mentors yet" body="Add a staff member above — they set their availability on the Mentoring page." /></div>
      ) : (
        <div className="mt-8 divide-y divide-line rounded-[14px] border border-line bg-white">
          {mentors.map((m) => (
            <div key={m.id} className="flex items-center gap-3 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-ink">
                  {m.name} {m.rating !== null && <span className="mono text-xs text-amber">★ {m.rating}</span>}
                </p>
                <p className="mono truncate text-[11px] uppercase tracking-widest text-muted">
                  {m.expertise.join(" · ")} ·{" "}
                  {m.course_ids.length === 0
                    ? "all courses"
                    : (coursesData?.data ?? []).filter((c) => m.course_ids.includes(c.id)).map((c) => c.name).join(", ") || `${m.course_ids.length} courses`}{" "}
                  · {m.booked} booked · {m.completed} done · {m.no_shows} no-show
                </p>
              </div>
              <span className={`mono rounded-full px-2.5 py-0.5 text-[11px] uppercase tracking-widest ${m.is_active ? "bg-verify-bg text-verify" : "bg-paper text-muted"}`}>
                {m.is_active ? "active" : "off"}
              </span>
              <button onClick={() => toggle.mutate(m)} className="text-xs text-muted hover:underline">
                {m.is_active ? "Disable" : "Enable"}
              </button>
            </div>
          ))}
        </div>
      )}

      <h2 className="mt-10 text-sm font-semibold uppercase tracking-widest text-muted">Recent sessions</h2>
      {sessions.length === 0 ? (
        <p className="mt-3 text-sm text-muted">No sessions yet.</p>
      ) : (
        <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
          {sessions.map((s) => (
            <div key={s.id} className="flex items-center gap-3 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-ink">{s.student} × {s.mentor}</p>
                <p className="mono text-[11px] uppercase tracking-widest text-muted">
                  {ist(s.starts_at)} IST · {s.purpose.replace("_", " ")} · {s.status.replace("_", " ")}
                  {s.no_show_side && ` (${s.no_show_side})`}
                  {s.feedback_score !== null && ` · score ${s.feedback_score}`}
                  {s.student_rating !== null && ` · ★ ${s.student_rating}`}
                </p>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

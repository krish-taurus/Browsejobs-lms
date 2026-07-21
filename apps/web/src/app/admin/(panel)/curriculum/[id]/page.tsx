"use client";

import Link from "next/link";
import { use, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type Lesson = { id: number; title: string; type: string };
type Topic = { id: number; name: string; mock_enabled: boolean; lessons: Lesson[] };
type Module = { id: number; name: string; topics: Topic[] };
type CourseTree = { id: number; code: string; name: string; modules: Module[] };

type ModuleHit = {
  id: number;
  name: string;
  topics_count: number;
  course: { id: number; name: string; program: { id: number; name: string } | null } | null;
};

/** Search existing modules and clone one (with its topics + lessons) into this course. */
function ReuseModule({
  courseId,
  onCloned,
  onError,
}: {
  courseId: number;
  onCloned: () => void;
  onError: (m: string) => void;
}) {
  const [q, setQ] = useState("");
  const [busyId, setBusyId] = useState<number | null>(null);

  const { data, isFetching } = useQuery({
    queryKey: ["admin", "module-search", q],
    queryFn: () =>
      apiJson<{ data: ModuleHit[] }>(`/api/v1/admin/modules/search?q=${encodeURIComponent(q)}`),
    enabled: q.trim().length >= 2,
  });

  const clone = async (moduleId: number) => {
    setBusyId(moduleId);
    try {
      await apiJson(`/api/v1/admin/modules/${moduleId}/clone`, {
        method: "POST",
        body: JSON.stringify({ course_id: courseId }),
      });
      setQ("");
      onCloned();
    } catch (err) {
      onError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not copy the module.");
    } finally {
      setBusyId(null);
    }
  };

  const hits = (data?.data ?? []).filter((m) => m.course?.id !== courseId);

  return (
    <div className="mt-3">
      <input
        value={q}
        onChange={(e) => setQ(e.target.value)}
        placeholder="Search modules by name…"
        className="w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
      />
      {q.trim().length >= 2 && (
        <div className="mt-3 space-y-2">
          {isFetching && <div className="shimmer h-12 rounded-[10px]" />}
          {!isFetching && hits.length === 0 && (
            <p className="text-sm text-muted">No other modules match “{q}”.</p>
          )}
          {hits.map((m) => (
            <div
              key={m.id}
              className="flex items-center justify-between gap-3 rounded-[10px] border border-line bg-paper px-3 py-2"
            >
              <span className="min-w-0">
                <span className="block truncate text-sm font-semibold text-ink">{m.name}</span>
                <span className="mono block truncate text-xs text-muted">
                  {m.course?.program?.name ? `${m.course.program.name} · ` : ""}
                  {m.course?.name ?? "—"} · {m.topics_count} topics
                </span>
              </span>
              <button
                onClick={() => clone(m.id)}
                disabled={busyId === m.id}
                className="shrink-0 rounded-full bg-trust px-3.5 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
              >
                {busyId === m.id ? "Copying…" : "Copy here"}
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

const LESSON_TYPES = [
  "live_class", "video", "notes", "quiz", "coding_lab", "assignment", "project", "mock_milestone",
];

function AddInline({
  placeholder,
  onAdd,
  withType = false,
}: {
  placeholder: string;
  onAdd: (value: string, type?: string) => Promise<unknown>;
  withType?: boolean;
}) {
  const [value, setValue] = useState("");
  const [type, setType] = useState(LESSON_TYPES[0]);
  const [busy, setBusy] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!value.trim()) return;
    setBusy(true);
    try {
      await onAdd(value.trim(), withType ? type : undefined);
      setValue("");
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={submit} className="flex flex-wrap items-center gap-2">
      <input
        value={value}
        onChange={(e) => setValue(e.target.value)}
        placeholder={placeholder}
        className="min-w-40 flex-1 rounded-[10px] border border-line bg-white px-3 py-1.5 text-sm text-ink outline-none focus:border-trust"
      />
      {withType && (
        <select
          value={type}
          onChange={(e) => setType(e.target.value)}
          className="rounded-[10px] border border-line bg-white px-2 py-1.5 text-xs text-ink outline-none"
        >
          {LESSON_TYPES.map((t) => (
            <option key={t} value={t}>{t.replace("_", " ")}</option>
          ))}
        </select>
      )}
      <button
        disabled={busy || !value.trim()}
        className="rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
      >
        Add
      </button>
    </form>
  );
}

export default function AdminCourseTreePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "course", id],
    queryFn: () => apiJson<{ data: CourseTree }>(`/api/v1/admin/courses/${id}`),
  });

  const refresh = () => queryClient.invalidateQueries({ queryKey: ["admin", "course", id] });

  const mutate = useMutation({
    mutationFn: ({ path, method, body }: { path: string; method: string; body?: object }) =>
      apiJson(path, { method, body: body ? JSON.stringify(body) : undefined }),
    onSuccess: refresh,
    onError: (err) =>
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed."),
  });

  const course = data?.data;

  return (
    <div className="mx-auto max-w-5xl">
      <Link href="/admin/curriculum" className="text-sm text-trust hover:underline">← All programs</Link>
      <p className="kicker mt-4 text-trust">Curriculum · {course?.code}</p>
      <h1 className="display mt-2 text-3xl text-ink">{course?.name ?? "…"}</h1>

      {error && (
        <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">
          {error}{" "}
          <button onClick={() => setError(null)} className="mono underline">dismiss</button>
        </p>
      )}

      {isLoading ? (
        <div className="mt-8 space-y-3">
          {Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-24 rounded-[14px]" />)}
        </div>
      ) : (
        <div className="mt-8 space-y-5">
          {course?.modules.map((m, mi) => (
            <section key={m.id} className="rounded-[14px] border border-line bg-white p-5">
              <div className="flex items-center justify-between gap-3">
                <h2 className="display text-lg text-ink">
                  <span className="mono mr-2 text-sm text-trust">{String(mi + 1).padStart(2, "0")}</span>
                  {m.name}
                </h2>
                <button
                  onClick={() => {
                    if (window.confirm(`Delete module “${m.name}” and everything in it?`)) {
                      mutate.mutate({ path: `/api/v1/admin/modules/${m.id}`, method: "DELETE" });
                    }
                  }}
                  className="text-xs font-semibold text-warn hover:underline"
                >
                  Delete module
                </button>
              </div>

              <div className="mt-4 space-y-3 border-l-2 border-line pl-4">
                {m.topics.map((t) => (
                  <div key={t.id} className="rounded-[10px] bg-paper p-3.5">
                    <div className="flex items-center justify-between gap-3">
                      <p className="font-semibold text-ink">{t.name}</p>
                      <div className="flex items-center gap-3">
                        <label className="flex cursor-pointer items-center gap-1.5 text-xs text-muted" title="Nudge students into a practice interview when they finish this topic">
                          <input
                            type="checkbox"
                            checked={t.mock_enabled}
                            onChange={(e) =>
                              mutate.mutate({
                                path: `/api/v1/admin/topics/${t.id}`,
                                method: "PATCH",
                                body: { mock_enabled: e.target.checked },
                              })
                            }
                            className="accent-trust"
                          />
                          Mock nudge
                        </label>
                        <button
                          onClick={() => {
                            if (window.confirm(`Delete topic “${t.name}”?`)) {
                              mutate.mutate({ path: `/api/v1/admin/topics/${t.id}`, method: "DELETE" });
                            }
                          }}
                          className="text-xs text-warn hover:underline"
                        >
                          Delete
                        </button>
                      </div>
                    </div>
                    <ul className="mt-2 space-y-1">
                      {t.lessons.map((l) => (
                        <li key={l.id} className="flex items-center justify-between gap-3 text-sm">
                          <span className="text-ink2/85">
                            {l.title}{" "}
                            <span className="mono text-[10px] uppercase tracking-widest text-muted">
                              {l.type.replace("_", " ")}
                            </span>
                          </span>
                          <button
                            onClick={() => mutate.mutate({ path: `/api/v1/admin/lessons/${l.id}`, method: "DELETE" })}
                            className="text-xs text-muted hover:text-warn"
                            aria-label={`Delete lesson ${l.title}`}
                          >
                            ✕
                          </button>
                        </li>
                      ))}
                    </ul>
                    <div className="mt-2.5">
                      <AddInline
                        placeholder="New lesson title…"
                        withType
                        onAdd={(title, type) =>
                          mutate.mutateAsync({
                            path: "/api/v1/admin/lessons",
                            method: "POST",
                            body: { topic_id: t.id, title, type },
                          })
                        }
                      />
                    </div>
                  </div>
                ))}
                <AddInline
                  placeholder="New topic name…"
                  onAdd={(name) =>
                    mutate.mutateAsync({
                      path: "/api/v1/admin/topics",
                      method: "POST",
                      body: { module_id: m.id, name },
                    })
                  }
                />
              </div>
            </section>
          ))}

          <section className="rounded-[14px] border border-dashed border-line bg-white p-5">
            <p className="kicker text-muted">Add module</p>
            <div className="mt-3">
              <AddInline
                placeholder="New module name…"
                onAdd={(name) =>
                  mutate.mutateAsync({
                    path: "/api/v1/admin/modules",
                    method: "POST",
                    body: { course_id: Number(id), name },
                  })
                }
              />
            </div>

            <div className="mt-5 border-t border-line pt-5">
              <p className="kicker text-muted">Or reuse an existing module</p>
              <p className="mt-1 text-sm text-ink2/70">
                Search a module you already built in another program — it copies
                here with all its topics and lessons. Edits stay independent.
              </p>
              <ReuseModule
                courseId={Number(id)}
                onCloned={() => {
                  setError(null);
                  void refresh();
                }}
                onError={(m) => setError(m)}
              />
            </div>
          </section>
        </div>
      )}
    </div>
  );
}

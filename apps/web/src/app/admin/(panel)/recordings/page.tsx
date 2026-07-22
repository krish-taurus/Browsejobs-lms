"use client";

import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";

/**
 * Recordings library grouped by batch: every class recording under its batch, with
 * the class name, date/time and the trainer who took it — so a specific class's
 * recording is easy to find. Zoom Cloud recordings open in place (watch link + passcode).
 */

type Rec = {
  id: number;
  title: string;
  session_title: string;
  topic: string | null;
  recorded_on: string | null;
  duration_seconds: number | null;
  trainer: string | null;
  watch_url: string | null;
  passcode: string | null;
};
type Group = {
  batch: { id: number; number: string; course_code: string | null; course_name: string | null };
  recordings: Rec[];
};

function ist(iso: string | null): string {
  if (!iso) return "—";
  return new Date(iso).toLocaleString("en-IN", {
    timeZone: "Asia/Kolkata", weekday: "short", day: "numeric", month: "short", hour: "numeric", minute: "2-digit",
  });
}
function fmtDuration(s: number | null): string {
  if (!s) return "";
  const m = Math.round(s / 60);
  return m >= 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${m}m`;
}

export default function RecordingsPage() {
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "recordings"],
    queryFn: () => apiJson<{ data: Group[] }>("/api/v1/admin/recordings"),
  });

  if (isLoading) return <div className="shimmer h-40 w-full rounded-[14px]" />;
  const groups = data?.data ?? [];

  return (
    <div className="mx-auto max-w-5xl">
      <p className="mono text-[11px] uppercase tracking-widest text-trust">Recordings</p>
      <h1 className="display mt-1 text-2xl text-ink">Class recordings by batch</h1>
      <p className="mt-1 text-sm text-muted">
        Every class recording, grouped under its batch and class. Recordings stream from Zoom Cloud — the
        Watch link opens the recording (use the passcode if Zoom asks).
      </p>

      {groups.length === 0 ? (
        <div className="mt-8 rounded-[14px] border border-dashed border-line bg-paper p-8 text-center">
          <p className="text-sm text-muted">
            No recordings yet. Once a recorded class ends, Zoom sends the recording here automatically —
            grouped under its batch.
          </p>
        </div>
      ) : (
        <div className="mt-8 space-y-8">
          {groups.map((g) => (
            <section key={g.batch.id}>
              <div className="flex items-baseline gap-2">
                <h2 className="display text-lg text-ink">{g.batch.number}</h2>
                <span className="mono text-xs uppercase tracking-widest text-muted">{g.batch.course_code}</span>
                <span className="text-sm text-muted">· {g.batch.course_name}</span>
              </div>

              <div className="mt-3 divide-y divide-line rounded-[14px] border border-line bg-white">
                {g.recordings.map((r) => (
                  <div key={r.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                    <div className="min-w-0">
                      <span className="block text-sm font-medium text-ink">
                        {r.session_title}{r.topic && r.topic !== r.session_title ? ` · ${r.topic}` : ""}
                      </span>
                      <span className="mono block text-xs text-muted">
                        {ist(r.recorded_on)} IST
                        {r.duration_seconds ? ` · ${fmtDuration(r.duration_seconds)}` : ""}
                        {r.trainer ? ` · ${r.trainer}` : ""}
                      </span>
                    </div>
                    {r.watch_url ? (
                      <a
                        href={r.watch_url}
                        target="_blank"
                        rel="noreferrer"
                        className="shrink-0 rounded-full border border-line px-4 py-1.5 text-xs font-semibold text-trust transition-colors hover:bg-paper"
                      >
                        Watch{r.passcode ? ` · passcode ${r.passcode}` : ""}
                      </a>
                    ) : (
                      <span className="mono shrink-0 text-xs text-muted">processing…</span>
                    )}
                  </div>
                ))}
              </div>
            </section>
          ))}
        </div>
      )}
    </div>
  );
}

"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type Recording = { id: number; title: string; status: string; watch_url: string | null; passcode: string | null };
type Session = {
  id: number;
  title: string;
  scheduled_start: string | null;
  scheduled_end: string | null;
  status: string;
  has_meeting: boolean;
  can_start: boolean;
  recordings?: Recording[];
};

const STATUS_STYLES: Record<string, string> = {
  scheduled: "bg-sky text-deep",
  live: "bg-verify-bg text-verify",
  ended: "bg-paper text-muted",
  cancelled: "bg-warn/10 text-warn",
};

const inputCls =
  "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

function fmt(iso: string | null): string {
  if (!iso) return "—";
  return new Date(iso).toLocaleString(undefined, {
    weekday: "short", day: "numeric", month: "short", hour: "2-digit", minute: "2-digit",
  });
}

/** datetime-local value ("YYYY-MM-DDTHH:mm", local) → ISO for the API. */
function toIso(local: string): string {
  return new Date(local).toISOString();
}

const WEEKDAYS: { iso: number; label: string }[] = [
  { iso: 1, label: "Mon" }, { iso: 2, label: "Tue" }, { iso: 3, label: "Wed" },
  { iso: 4, label: "Thu" }, { iso: 5, label: "Fri" }, { iso: 6, label: "Sat" }, { iso: 7, label: "Sun" },
];

export function BatchClasses({ batchId }: { batchId: string }) {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [schedule, setSchedule] = useState({ title: "", start: "", end: "", record: true });
  const [reschedulingId, setReschedulingId] = useState<number | null>(null);
  const [reschedule, setReschedule] = useState({ start: "", reason: "" });
  const [showSeries, setShowSeries] = useState(false);
  const [series, setSeries] = useState({
    startDate: "", time: "18:00", duration: 90, count: 10,
    weekdays: [1, 2, 3, 4, 5], mapTopics: true, record: true,
    perDayTime: false, times: {} as Record<number, string>,
  });
  const [seriesMsg, setSeriesMsg] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "batch-sessions", batchId],
    queryFn: () => apiJson<{ data: Session[] }>(`/api/v1/admin/batches/${batchId}/sessions`),
  });

  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "batch-sessions", batchId] });
  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const create = useMutation({
    mutationFn: () =>
      apiJson(`/api/v1/admin/batches/${batchId}/sessions`, {
        method: "POST",
        body: JSON.stringify({
          title: schedule.title,
          scheduled_start: toIso(schedule.start),
          scheduled_end: schedule.end ? toIso(schedule.end) : null,
          record: schedule.record,
        }),
      }),
    onSuccess: () => { setSchedule({ title: "", start: "", end: "", record: true }); setError(null); void refresh(); },
    onError,
  });

  const doReschedule = useMutation({
    mutationFn: (id: number) =>
      apiJson(`/api/v1/admin/sessions/${id}/reschedule`, {
        method: "POST",
        body: JSON.stringify({ scheduled_start: toIso(reschedule.start), reason: reschedule.reason }),
      }),
    onSuccess: () => { setReschedulingId(null); setReschedule({ start: "", reason: "" }); setError(null); void refresh(); },
    onError,
  });

  const cancel = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      apiJson(`/api/v1/admin/sessions/${id}/cancel`, { method: "POST", body: JSON.stringify({ reason }) }),
    onSuccess: () => { setError(null); void refresh(); },
    onError,
  });

  // Go live as host: opens the Zoom host link. Gated + time-windowed server-side.
  const start = useMutation({
    mutationFn: (id: number) =>
      apiJson<{ data: { start_url: string } }>(`/api/v1/admin/sessions/${id}/start`, { method: "POST" }),
    onSuccess: (r) => { setError(null); window.open(r.data.start_url, "_blank", "noopener"); },
    onError: (err) => { onError(err); void refresh(); },
  });

  // Repair: create the Zoom meeting for a class scheduled before Zoom was connected.
  // Runs synchronously, so we get immediate success/failure feedback.
  const createMeeting = useMutation({
    mutationFn: (id: number) =>
      apiJson(`/api/v1/admin/sessions/${id}/create-meeting`, { method: "POST" }),
    onSuccess: () => { setError(null); setNotice("Zoom meeting created — the Start class button appears near class time."); void refresh(); },
    onError: (err) => { setNotice(null); onError(err); },
  });

  const createSeries = useMutation({
    mutationFn: () =>
      apiJson<{ data: Session[] }>(`/api/v1/admin/batches/${batchId}/sessions/series`, {
        method: "POST",
        body: JSON.stringify({
          weekdays: series.weekdays,
          time: series.time,
          // Per-day times only for the selected days; server falls back to `time`.
          times: series.perDayTime
            ? Object.fromEntries(series.weekdays.map((d) => [d, series.times[d] || series.time]))
            : undefined,
          duration_minutes: series.duration,
          count: series.count,
          start_date: series.startDate,
          map_topics: series.mapTopics,
          record: series.record,
        }),
      }),
    onSuccess: (res) => {
      setError(null);
      setSeriesMsg(`Scheduled ${res.data.length} class${res.data.length === 1 ? "" : "es"}.`);
      void refresh();
    },
    onError: (err) => { setSeriesMsg(null); onError(err); },
  });

  const toggleWeekday = (iso: number) =>
    setSeries((s) => ({ ...s, weekdays: s.weekdays.includes(iso) ? s.weekdays.filter((d) => d !== iso) : [...s.weekdays, iso].sort() }));

  const applyPreset = (days: number[]) => setSeries((s) => ({ ...s, weekdays: [...days] }));
  const PRESETS: { label: string; days: number[] }[] = [
    { label: "Every day", days: [1, 2, 3, 4, 5, 6, 7] },
    { label: "Weekdays", days: [1, 2, 3, 4, 5] },
    { label: "Alternate (M/W/F)", days: [1, 3, 5] },
    { label: "Weekends", days: [6, 7] },
  ];

  const sessions = data?.data ?? [];

  return (
    <div className="mt-10">
      <h2 className="display text-xl text-ink">Classes</h2>
      <p className="mt-1 text-sm text-muted">
        Reschedule or cancel a class and the whole batch is notified by email and WhatsApp at once.
        An extra session lands its recording in this batch&rsquo;s library automatically.
      </p>

      {error && (
        <p className="mt-3 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">
          {error} <button onClick={() => setError(null)} className="mono underline">dismiss</button>
        </p>
      )}
      {notice && (
        <p className="mt-3 rounded-[10px] bg-verify/10 px-3 py-2 text-sm text-verify">
          {notice} <button onClick={() => setNotice(null)} className="mono underline">dismiss</button>
        </p>
      )}

      {/* Schedule a class */}
      <form
        onSubmit={(e) => { e.preventDefault(); create.mutate(); }}
        className="mt-4 rounded-[14px] border border-line bg-white p-5"
      >
        <p className="kicker text-muted">Schedule a class</p>
        <div className="mt-3 space-y-2.5">
          <input
            required value={schedule.title}
            onChange={(e) => setSchedule({ ...schedule, title: e.target.value })}
            placeholder="Class title, e.g. SQL — Window functions" className={`${inputCls} w-full`} maxLength={200}
          />
          <div className="flex flex-wrap gap-2.5">
            <label className="flex flex-1 flex-col gap-1 text-xs text-muted">Starts
              <input required type="datetime-local" value={schedule.start}
                onChange={(e) => setSchedule({ ...schedule, start: e.target.value })} className={inputCls} />
            </label>
            <label className="flex flex-1 flex-col gap-1 text-xs text-muted">Ends (optional)
              <input type="datetime-local" value={schedule.end}
                onChange={(e) => setSchedule({ ...schedule, end: e.target.value })} className={inputCls} />
            </label>
          </div>
          <label className="flex items-center gap-2 text-sm text-ink">
            <input type="checkbox" checked={schedule.record}
              onChange={(e) => setSchedule({ ...schedule, record: e.target.checked })}
              className="h-4 w-4 rounded border-line text-trust" />
            Record this class to the cloud
          </label>
        </div>
        <button
          disabled={create.isPending || !schedule.title || !schedule.start}
          className="mt-3 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
        >
          {create.isPending ? "Scheduling…" : "Schedule class"}
        </button>
      </form>

      {/* Schedule a whole series */}
      <div className="mt-3 rounded-[14px] border border-line bg-white p-5">
        <button
          type="button"
          onClick={() => { setShowSeries((v) => !v); setSeriesMsg(null); }}
          className="flex w-full items-center justify-between text-left"
        >
          <span>
            <span className="kicker text-muted">Schedule a recurring series</span>
            <span className="mt-0.5 block text-xs text-muted">Generate the whole calendar in one go — daily or on set days.</span>
          </span>
          <span className="mono text-lg text-muted">{showSeries ? "−" : "+"}</span>
        </button>

        {showSeries && (
          <form onSubmit={(e) => { e.preventDefault(); createSeries.mutate(); }} className="mt-4 space-y-3">
            <div>
              <span className="text-xs text-muted">Quick pick</span>
              <div className="mt-1.5 flex flex-wrap gap-1.5">
                {PRESETS.map((p) => (
                  <button
                    key={p.label} type="button" onClick={() => applyPreset(p.days)}
                    className="rounded-full border border-line px-3 py-1 text-[12px] text-ink transition-colors hover:border-trust hover:text-trust"
                  >
                    {p.label}
                  </button>
                ))}
              </div>
            </div>
            <div>
              <span className="text-xs text-muted">Class days</span>
              <div className="mt-1.5 flex flex-wrap gap-1.5">
                {WEEKDAYS.map((d) => (
                  <button
                    key={d.iso} type="button" onClick={() => toggleWeekday(d.iso)}
                    className={`mono rounded-full px-3 py-1 text-[12px] transition-colors ${series.weekdays.includes(d.iso) ? "bg-trust text-white" : "border border-line text-ink hover:bg-paper"}`}
                  >
                    {d.label}
                  </button>
                ))}
              </div>
            </div>
            <div className="flex flex-wrap gap-2.5">
              <label className="flex flex-1 flex-col gap-1 text-xs text-muted">First class date
                <input required type="date" value={series.startDate}
                  onChange={(e) => setSeries({ ...series, startDate: e.target.value })} className={inputCls} />
              </label>
              {!series.perDayTime && (
                <label className="flex flex-col gap-1 text-xs text-muted">Time (all days)
                  <input required type="time" value={series.time}
                    onChange={(e) => setSeries({ ...series, time: e.target.value })} className={inputCls} />
                </label>
              )}
            </div>

            <label className="flex items-center gap-2 text-sm text-ink">
              <input type="checkbox" checked={series.perDayTime}
                onChange={(e) => setSeries({ ...series, perDayTime: e.target.checked })}
                className="h-4 w-4 rounded border-line text-trust" />
              Set a different time per day
            </label>
            {series.perDayTime && (
              <div className="rounded-[12px] border border-line bg-paper p-3">
                {series.weekdays.length === 0 ? (
                  <p className="text-xs text-muted">Pick class days above to set their times.</p>
                ) : (
                  <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {series.weekdays.map((iso) => (
                      <label key={iso} className="flex items-center justify-between gap-2 text-sm text-ink">
                        <span className="mono text-xs">{WEEKDAYS.find((w) => w.iso === iso)?.label}</span>
                        <input
                          type="time"
                          value={series.times[iso] ?? series.time}
                          onChange={(e) => setSeries((s) => ({ ...s, times: { ...s.times, [iso]: e.target.value } }))}
                          className={`${inputCls} w-28`}
                        />
                      </label>
                    ))}
                  </div>
                )}
              </div>
            )}
            <div className="flex flex-wrap gap-2.5">
              <label className="flex flex-1 flex-col gap-1 text-xs text-muted">Duration (min)
                <input required type="number" min={15} max={480} value={series.duration}
                  onChange={(e) => setSeries({ ...series, duration: Number(e.target.value) })} className={inputCls} />
              </label>
              <label className="flex flex-1 flex-col gap-1 text-xs text-muted">Number of classes
                <input required type="number" min={1} max={120} value={series.count}
                  onChange={(e) => setSeries({ ...series, count: Number(e.target.value) })} className={inputCls} />
              </label>
            </div>
            <label className="flex items-center gap-2 text-sm text-ink">
              <input type="checkbox" checked={series.mapTopics}
                onChange={(e) => setSeries({ ...series, mapTopics: e.target.checked })}
                className="h-4 w-4 rounded border-line text-trust" />
              Title classes from the syllabus (one topic per class, in order)
            </label>
            <label className="flex items-center gap-2 text-sm text-ink">
              <input type="checkbox" checked={series.record}
                onChange={(e) => setSeries({ ...series, record: e.target.checked })}
                className="h-4 w-4 rounded border-line text-trust" />
              Record each class to the cloud
            </label>
            {seriesMsg && <p className="mono text-xs text-verify">{seriesMsg}</p>}
            <button
              disabled={createSeries.isPending || series.weekdays.length === 0 || !series.startDate || !series.time}
              className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              {createSeries.isPending ? "Scheduling…" : "Schedule series"}
            </button>
          </form>
        )}
      </div>

      {/* Session list */}
      {isLoading ? (
        <div className="mt-4 space-y-2">{Array.from({ length: 2 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-[14px]" />)}</div>
      ) : sessions.length === 0 ? (
        <p className="mt-4 rounded-[14px] border border-line bg-paper px-5 py-6 text-center text-sm text-muted">
          No classes scheduled yet.
        </p>
      ) : (
        <div className="mt-4 divide-y divide-line rounded-[14px] border border-line bg-white">
          {sessions.map((s) => (
            <div key={s.id} className="px-5 py-4">
              <div className="flex flex-wrap items-center gap-3">
                <span className="min-w-40 flex-1">
                  <span className="block font-semibold text-ink">{s.title}</span>
                  <span className="mono block text-xs text-muted">{fmt(s.scheduled_start)}</span>
                </span>
                <span className={`mono rounded-full px-2.5 py-1 text-[10px] uppercase tracking-widest ${STATUS_STYLES[s.status] ?? "bg-paper text-muted"}`}>
                  {s.status}
                </span>
                {s.can_start && (
                  <button
                    onClick={() => start.mutate(s.id)}
                    disabled={start.isPending}
                    className="rounded-full bg-trust px-4 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
                  >
                    {start.isPending ? "Opening…" : "Start class"}
                  </button>
                )}
                {!s.has_meeting && s.status === "scheduled" && (
                  <button
                    onClick={() => createMeeting.mutate(s.id)}
                    disabled={createMeeting.isPending}
                    className="rounded-full border border-line px-4 py-1.5 text-xs font-semibold text-ink transition-colors hover:bg-paper disabled:opacity-50"
                    title="Create the Zoom meeting for this class"
                  >
                    {createMeeting.isPending ? "Creating…" : "Create meeting"}
                  </button>
                )}
                {s.status === "scheduled" && (
                  <span className="flex gap-3 text-xs font-semibold">
                    <button
                      onClick={() => { setReschedulingId(reschedulingId === s.id ? null : s.id); setReschedule({ start: "", reason: "" }); }}
                      className="text-trust hover:underline"
                    >
                      Reschedule
                    </button>
                    <button
                      onClick={() => {
                        const reason = window.prompt(`Cancel "${s.title}"? This notifies the whole batch. Reason:`);
                        if (reason) cancel.mutate({ id: s.id, reason });
                      }}
                      className="text-warn hover:underline"
                    >
                      Cancel
                    </button>
                  </span>
                )}
              </div>

              {reschedulingId === s.id && (
                <form
                  onSubmit={(e) => { e.preventDefault(); doReschedule.mutate(s.id); }}
                  className="mt-3 flex flex-wrap items-end gap-2.5 rounded-[10px] bg-paper p-3"
                >
                  <label className="flex flex-col gap-1 text-xs text-muted">New time
                    <input required type="datetime-local" value={reschedule.start}
                      onChange={(e) => setReschedule({ ...reschedule, start: e.target.value })} className={inputCls} />
                  </label>
                  <input required value={reschedule.reason}
                    onChange={(e) => setReschedule({ ...reschedule, reason: e.target.value })}
                    placeholder="Reason (shown to students)" className={`${inputCls} min-w-48 flex-1`} maxLength={500} />
                  <button
                    disabled={doReschedule.isPending || !reschedule.start || !reschedule.reason}
                    className="rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                  >
                    {doReschedule.isPending ? "Saving…" : "Save & notify"}
                  </button>
                </form>
              )}

              {(s.recordings?.length ?? 0) > 0 && (
                <div className="mt-2 flex flex-wrap items-center gap-2 text-xs">
                  <span className="text-muted">Recording:</span>
                  {s.recordings!.map((r) =>
                    r.watch_url ? (
                      <a
                        key={r.id}
                        href={r.watch_url}
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-full border border-line px-3 py-1 font-semibold text-trust hover:bg-paper"
                      >
                        Watch{r.passcode ? ` · passcode ${r.passcode}` : ""}
                      </a>
                    ) : (
                      <span key={r.id} className="text-muted">{r.title} (processing)</span>
                    ),
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

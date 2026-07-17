"use client";

import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type Settings = {
  attendance_points: number;
  punctuality_points: number;
  quiz_points: number;
  lab_points: number;
  streak_day_points: number;
  mock_points: number;
  daily_cap: number;
  attendance_min_pct: number;
};

const FIELDS: { key: keyof Settings; label: string; hint: string }[] = [
  { key: "attendance_points", label: "Class attended", hint: "awarded once per live session" },
  { key: "punctuality_points", label: "On-time bonus", hint: "joined within the grace window" },
  { key: "quiz_points", label: "Quiz passed", hint: "once per quiz — retakes never re-award" },
  { key: "lab_points", label: "Lab solved", hint: "all hidden tests passing, once per lab" },
  { key: "streak_day_points", label: "Streak day", hint: "each consecutive active day from day 2" },
  { key: "mock_points", label: "Mock taken", hint: "reserved — activates with P4 mock interviews" },
  { key: "daily_cap", label: "Daily cap", hint: "anti-gaming: max points a student can earn per day" },
  { key: "attendance_min_pct", label: "Attendance threshold %", hint: "minimum % of the class attended to earn points" },
];

const inputCls =
  "w-24 rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust mono";

export default function AdminPointsPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [settings, setSettings] = useState<Settings | null>(null);

  const query = useQuery({
    queryKey: ["admin", "points-settings"],
    queryFn: () => apiJson<{ data: Settings }>("/api/v1/admin/points-settings"),
  });

  useEffect(() => {
    if (query.data) setSettings(query.data.data);
  }, [query.data]);

  const save = useMutation({
    mutationFn: (body: Partial<Settings>) =>
      apiJson("/api/v1/admin/points-settings", { method: "PUT", body: JSON.stringify(body) }),
    onSuccess: () => {
      setError(null);
      void qc.invalidateQueries({ queryKey: ["admin", "points-settings"] });
    },
    onError: (err) =>
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed."),
  });

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Leaderboard</p>
      <h1 className="display mt-2 text-3xl text-ink">Points &amp; weights</h1>
      <p className="mt-2 max-w-xl text-sm text-muted">
        The Prep Score is transparent: these weights decide what each verified
        action earns. Points are only ever minted server-side, once per source,
        under the daily cap.
      </p>

      {error && (
        <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">
          {error} <button onClick={() => setError(null)} className="mono underline">dismiss</button>
        </p>
      )}

      {!settings ? (
        <div className="shimmer mt-8 h-64 rounded-[14px]" />
      ) : (
        <div className="mt-8 divide-y divide-line rounded-[14px] border border-line bg-white">
          {FIELDS.map((f) => (
            <label key={f.key} className="flex items-center justify-between gap-4 px-5 py-4">
              <span>
                <span className="block font-semibold text-ink">{f.label}</span>
                <span className="block text-xs text-muted">{f.hint}</span>
              </span>
              <input
                type="number"
                min={0}
                value={settings[f.key]}
                onChange={(e) => setSettings({ ...settings, [f.key]: Number(e.target.value) })}
                onBlur={() => save.mutate({ [f.key]: settings[f.key] })}
                className={inputCls}
              />
            </label>
          ))}
        </div>
      )}
    </div>
  );
}

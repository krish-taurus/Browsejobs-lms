"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type RiskRow = {
  user_id: number;
  name: string | null;
  phone: string | null;
  risk_dropout: number;
  risk_band: "low" | "medium" | "high";
  engagement: number;
  pri: number;
  mover: number;
  red_flags: string[];
  script: string;
};

type RiskBoard = {
  high_risk: number;
  movers: RiskRow[];
  students: RiskRow[];
};

const BAND_PILL: Record<RiskRow["risk_band"], string> = {
  high: "bg-warn/10 text-warn border-warn/30",
  medium: "bg-sky text-deep border-line",
  low: "bg-verify-bg text-verify border-verify/30",
};

const FLAG_LABEL: Record<string, string> = {
  fee_overdue: "Fee overdue",
  low_engagement: "Disengaged",
  low_attendance: "Low attendance",
  stalled: "Stalled",
};

function flagLabel(f: string): string {
  return FLAG_LABEL[f] ?? f.replace(/_/g, " ");
}

/** Positive mover = risk rising (worse) = warn; negative = improving = verify. */
function Mover({ delta }: { delta: number }) {
  if (delta === 0) return <span className="mono text-xs text-muted">—</span>;
  const up = delta > 0;
  return (
    <span className={`mono text-xs font-semibold ${up ? "text-warn" : "text-verify"}`}>
      {up ? "▲" : "▼"} {Math.abs(delta)}
    </span>
  );
}

function ScriptButton({ script }: { script: string }) {
  const [copied, setCopied] = useState(false);
  return (
    <button
      type="button"
      onClick={() => {
        void navigator.clipboard?.writeText(script).then(() => {
          setCopied(true);
          setTimeout(() => setCopied(false), 1500);
        });
      }}
      className="mt-2 rounded-full border border-line px-3 py-1 text-xs font-medium text-trust transition-colors hover:bg-sky"
    >
      {copied ? "Copied ✓" : "Copy script"}
    </button>
  );
}

export default function RiskPage() {
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "risk"],
    queryFn: () => apiJson<{ data: RiskBoard }>("/api/v1/admin/risk"),
  });
  const d = data?.data;

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Coach</p>
      <h1 className="display mt-2 text-3xl text-ink">Student risk</h1>
      <p className="mt-1 text-sm text-muted">
        Rule-based dropout risk from learning telemetry — sorted so the students who need a call today rise to the top.
      </p>

      {isLoading || !d ? (
        <div className="mt-8 space-y-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="shimmer h-16 rounded-[14px]" />
          ))}
        </div>
      ) : (
        <>
          <div className="mt-6 grid gap-4 sm:grid-cols-3">
            <div className="rounded-[14px] border border-line bg-white p-5">
              <div className="mono text-2xl text-warn">{d.high_risk}</div>
              <p className="mt-1 text-sm text-muted">High risk</p>
            </div>
            <div className="rounded-[14px] border border-line bg-white p-5">
              <div className="mono text-2xl text-ink">{d.movers.length}</div>
              <p className="mt-1 text-sm text-muted">Rising today</p>
            </div>
            <div className="rounded-[14px] border border-line bg-white p-5">
              <div className="mono text-2xl text-ink">{d.students.length}</div>
              <p className="mt-1 text-sm text-muted">Students scored</p>
            </div>
          </div>

          {d.students.length === 0 ? (
            <div className="mt-8">
              <EmptyState
                title="No students scored yet"
                body="Once students have learning activity, the nightly scoring run surfaces at-risk students here with intervention scripts."
              />
            </div>
          ) : (
            <div className="mt-8 space-y-3">
              {d.students.map((s) => (
                <div key={s.user_id} className="rounded-[14px] border border-line bg-white p-5">
                  <div className="flex flex-wrap items-center gap-3">
                    <span
                      className={`mono rounded-full border px-2.5 py-0.5 text-xs font-semibold ${BAND_PILL[s.risk_band]}`}
                    >
                      {s.risk_dropout}
                    </span>
                    <span className="font-semibold text-ink">{s.name ?? "Student"}</span>
                    {s.phone && <span className="mono text-xs text-muted">{s.phone}</span>}
                    <Mover delta={s.mover} />
                    <span className="mono ml-auto text-xs text-muted">
                      PRI {s.pri} · Eng {s.engagement}
                    </span>
                  </div>

                  {s.red_flags.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-2">
                      {s.red_flags.map((f) => (
                        <span
                          key={f}
                          className="mono rounded-full bg-warn/10 px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wide text-warn"
                        >
                          {flagLabel(f)}
                        </span>
                      ))}
                    </div>
                  )}

                  <div className="mt-3 rounded-[10px] border-l-2 border-trust bg-sky/40 px-4 py-3 text-sm text-ink">
                    {s.script}
                  </div>
                  <ScriptButton script={s.script} />
                </div>
              ))}
            </div>
          )}
        </>
      )}
    </div>
  );
}

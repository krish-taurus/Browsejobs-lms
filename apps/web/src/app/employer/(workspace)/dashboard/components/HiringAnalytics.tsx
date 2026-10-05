"use client";

import { useState } from "react";
import type { DashboardData } from "@/lib/employer";
import { LightFunnel, LightHistogram, LightTrendChart } from "@/components/employer/LightCharts";
import { BriefcaseIcon } from "@/components/employer/icons";

type Tab = "applications" | "grades" | "funnel";

const TABS: { key: Tab; label: string }[] = [
  { key: "applications", label: "Applications" },
  { key: "grades", label: "Grades" },
  { key: "funnel", label: "Funnel" },
];

function EmptyState({ children }: { children: React.ReactNode }) {
  return (
    <div
      className="flex h-[130px] items-center justify-center rounded-xl border border-dashed px-6 text-center text-[13px]"
      style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-muted)" }}
    >
      {children}
    </div>
  );
}

export function HiringAnalytics({ data }: { data: DashboardData }) {
  const [tab, setTab] = useState<Tab>("applications");
  const hasApplications = data.total_applications > 0;
  const hasJobs = data.active_jobs > 0;

  return (
    <div className="rounded-[var(--bj-dash-radius)] border bg-white p-5 sm:p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-2.5">
          <span style={{ color: "var(--bj-dash-primary)" }}><BriefcaseIcon /></span>
          <h2 className="text-base font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Hiring analytics</h2>
          <div className="ml-2 flex gap-1 rounded-full p-0.5" style={{ background: "var(--bj-dash-canvas)" }}>
            {TABS.map((t) => (
              <button
                key={t.key}
                type="button"
                onClick={() => setTab(t.key)}
                className="rounded-full px-3 py-1 text-sm font-medium transition-colors"
                style={tab === t.key
                  ? { background: "white", color: "var(--bj-dash-primary)", boxShadow: "var(--bj-dash-shadow)" }
                  : { color: "var(--bj-dash-muted)" }}
              >
                {t.label}
              </button>
            ))}
          </div>
        </div>
        {!hasApplications && (
          <span className="text-xs" style={{ color: "var(--bj-dash-muted)" }}>Insights appear as interviews are completed.</span>
        )}
      </div>

      <div className="mt-5">
        {tab === "applications" && (
          hasApplications ? (
            <LightTrendChart points={data.trend} height={130} />
          ) : (
            <EmptyState>No applications yet — this fills in as candidates apply.</EmptyState>
          )
        )}
        {tab === "grades" && (
          hasApplications ? (
            <LightHistogram bands={data.score_distribution} threshold={70} />
          ) : (
            <EmptyState>Scores appear here once applicants are graded.</EmptyState>
          )
        )}
        {tab === "funnel" && (
          hasJobs ? (
            <LightFunnel stages={data.funnel} />
          ) : (
            <EmptyState>Publish a role to start filling this in.</EmptyState>
          )
        )}
      </div>
    </div>
  );
}

"use client";

import { useEffect, useState } from "react";
import { useAuth } from "@/lib/auth";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { Skeleton } from "@/components/employer/ui";
import { employerApi, type DashboardData } from "@/lib/employer";
import { DashboardHeading } from "./components/Heading";
import { InterviewBanner } from "./components/InterviewBanner";
import { SummaryStrip, type SummaryItem } from "./components/SummaryStrip";
import { OpenRoles } from "./components/OpenRoles";
import { ReadinessCard } from "./components/ReadinessCard";
import { InterviewStatusCard, ReviewQueueCard } from "./components/StatusCards";
import { HiringAnalytics } from "./components/HiringAnalytics";

/**
 * Employer dashboard — PRD-E emerald/ivory redesign (approved kit, Sept
 * 2026). Viewing-only overview: every control here either reads existing
 * data or navigates to an existing page. Posting a JD, sourcing candidates,
 * moving a candidate's stage, scheduling and the AI JD-composer chat
 * (formerly NeuralOps, above this page) live on their own pages now, not
 * here — see IMPLEMENT-DASHBOARD.md §2.
 */
export default function EmployerDashboardPage() {
  const { user } = useAuth();
  const { workspace } = useWorkspace();
  const [data, setData] = useState<DashboardData | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    setData(null);
    setFailed(false);
    employerApi.dashboard(workspace.id).then((res) => setData(res.data)).catch(() => setFailed(true));
  }, [workspace.id]);

  if (failed) {
    return (
      <div className="min-h-[60vh]">
        <p className="rounded-[var(--bj-dash-radius)] border bg-white p-8 text-sm" style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-muted)" }}>
          The dashboard could not load. Refresh to try again.
        </p>
      </div>
    );
  }

  if (!data) {
    return (
      <div className="space-y-5">
        <Skeleton className="h-16 w-full max-w-md" />
        <Skeleton className="h-52" />
        <Skeleton className="h-28" />
        <div className="grid gap-4 md:grid-cols-3">
          <Skeleton className="h-64 md:col-span-2" />
          <Skeleton className="h-64" />
        </div>
      </div>
    );
  }

  const summaryItems: SummaryItem[] = [
    { label: "Active jobs", value: data.active_jobs, scope: "All time", href: "/employer/jobs" },
    { label: "Total applicants", value: data.total_applications, scope: "All time", href: "/employer/pipeline" },
    {
      label: "Interviews in progress",
      value: data.interviews_in_flight,
      scope: data.interviews_in_flight > 0 ? "Running" : "None running",
      scopeTone: data.interviews_in_flight > 0 ? "running" : "neutral",
      href: "/employer/pipeline",
    },
    {
      label: "Open offers",
      value: data.offers_open,
      scope: data.offers_open === 0 ? "No offers pending" : "Open",
      href: "/employer/pipeline",
    },
  ];

  return (
    // The --bj-dash-* tokens and ivory canvas come from EmployerShell's own
    // root wrapper (.bj-employer-dashboard) further up the tree — plain CSS
    // custom-property inheritance, no need to reapply the class here.
    <div className="space-y-5 pb-6">
      <DashboardHeading
        displayName={user?.name ?? "there"}
        roles={data.pipeline.map((job) => ({ id: job.id, title: job.title }))}
      />

      <InterviewBanner awaitingReview={data.awaiting_review} />

      <SummaryStrip items={summaryItems} />

      <div className="grid items-stretch gap-4 lg:grid-cols-[1.7fr_1fr]">
        <OpenRoles pipeline={data.pipeline} />
        <ReadinessCard gradedApplications={data.graded_applications} totalApplications={data.total_applications} />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <InterviewStatusCard inProgress={data.interviews_in_flight} gradedLast7d={data.graded_last_7d} />
        <ReviewQueueCard awaitingReview={data.awaiting_review} />
      </div>

      <HiringAnalytics data={data} />

      <p className="font-mono text-[10px] leading-relaxed" style={{ color: "var(--bj-dash-muted)" }}>
        Counts reflect this workspace&apos;s live pipeline. Interview grading normally completes within the hour;
        delayed gradings are shown as pending — scores are never fabricated.
      </p>
    </div>
  );
}

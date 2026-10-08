"use client";

import Link from "next/link";
import { MockKindPage } from "@/components/mocks/MockKindPage";

export default function JobInterviewsPage() {
  return (
    <MockKindPage
      kind="job"
      emptyText="No job interviews yet. Take a role's AI interview from Jobs for You to apply."
    >
      <div className="mt-6 rounded-2xl border border-line bg-white p-6">
        <p className="text-xs font-semibold uppercase tracking-widest text-muted">Hiring directly on BrowseJobs</p>
        <p className="mt-2 text-sm text-ink">
          Each role has its own short AI interview. Take it first — it unlocks Apply, and your score goes
          to the employer with your CV.
        </p>
        <Link
          href="/jobs-for-you"
          className="mt-3 inline-block rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white"
        >
          See open roles →
        </Link>
      </div>
    </MockKindPage>
  );
}

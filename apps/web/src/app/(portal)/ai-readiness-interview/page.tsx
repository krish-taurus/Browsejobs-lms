"use client";

import Link from "next/link";
import { MockKindPage } from "@/components/mocks/MockKindPage";

export default function CvInterviewsPage() {
  return (
    <MockKindPage
      kind="cv"
      emptyText="No CV readiness interview yet. Upload your CV, then take it from Jobs for You."
    >
      <div className="mt-6 rounded-2xl border border-line bg-white p-6">
        <p className="text-xs font-semibold uppercase tracking-widest text-verify">Free · 15 questions</p>
        <p className="mt-2 text-sm text-ink">
          The interviewer asks about what&apos;s on your own CV. Finishing it is what lets employers find
          your profile in the talent pool.
        </p>
        <div className="mt-3 flex flex-wrap gap-2">
          <Link
            href="/jobs-for-you"
            className="inline-block rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white"
          >
            Take it from Jobs for You →
          </Link>
          <Link
            href="/cv"
            className="inline-block rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-ink hover:border-trust"
          >
            My CV
          </Link>
        </div>
      </div>
    </MockKindPage>
  );
}

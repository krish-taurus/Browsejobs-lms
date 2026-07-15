"use client";

import Link from "next/link";
import { useAuth } from "@/lib/auth";

export default function DashboardPage() {
  const { user } = useAuth();

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Dashboard</p>
      <h1 className="display mt-2 text-3xl text-ink">
        Welcome back, {user?.name?.split(" ")[0]}
      </h1>

      {/* Next Best Action — the loudest element on the dashboard (CLAUDE.md). */}
      <div className="mt-8 rounded-2xl bg-ink p-7 text-white shadow-lg">
        <p className="kicker text-sky/70">Next best action</p>
        <h2 className="display mt-2 text-xl">Join your next live class</h2>
        <p className="mt-1 text-sky/70">
          Your coach will surface the single most valuable thing to do next once
          your batch is active.
        </p>
        <Link
          href="/classes"
          className="mt-5 inline-block rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-ink transition-transform hover:-translate-y-0.5"
        >
          Go to my classes
        </Link>
      </div>

      <div className="mt-6 grid gap-4 sm:grid-cols-3">
        {[
          { label: "Attendance", value: "—" },
          { label: "Topics done", value: "—" },
          { label: "Mock score", value: "—" },
        ].map((s) => (
          <div key={s.label} className="rounded-2xl border border-line bg-white p-5">
            <div className="mono text-2xl text-ink">{s.value}</div>
            <p className="mt-1 text-sm text-muted">{s.label}</p>
          </div>
        ))}
      </div>
    </div>
  );
}

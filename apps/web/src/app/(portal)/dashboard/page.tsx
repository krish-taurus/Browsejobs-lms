"use client";

import { useAuth } from "@/lib/auth";
import { FeeWidget } from "@/components/portal/FeeWidget";
import { CoachPanel } from "@/components/portal/CoachPanel";

export default function DashboardPage() {
  const { user } = useAuth();

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Dashboard</p>
      <h1 className="display mt-2 text-3xl text-ink">
        Welcome back, {user?.name?.split(" ")[0]}
      </h1>

      <FeeWidget />

      {/* Coach Panel — Next Best Action, PRI ring, mastery, wins & focus (PRD §6.4). */}
      <CoachPanel />
    </div>
  );
}

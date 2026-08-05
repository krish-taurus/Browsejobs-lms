"use client";

import { useAuth } from "@/lib/auth";
import { FeeWidget } from "@/components/portal/FeeWidget";
import { FeeChoiceCard } from "@/components/portal/FeeChoiceCard";
import { NextClassCard } from "@/components/portal/NextClassCard";
import { QuizDueCard } from "@/components/portal/QuizDueCard";
import { CoachPanel } from "@/components/portal/CoachPanel";
import { LeaderboardCard } from "@/components/portal/LeaderboardCard";
import { MarketBriefCard } from "@/components/portal/MarketBriefCard";

export default function DashboardPage() {
  const { user } = useAuth();

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Dashboard</p>
      <h1 className="display mt-2 text-3xl text-ink">
        Welcome back, {user?.name?.split(" ")[0]}
      </h1>

      <FeeWidget />

      {/* Seat held but nothing agreed yet — pick full payment or an EMI plan.
          Renders nothing once a plan exists; FeeWidget above takes over. */}
      <FeeChoiceCard />

      {/* Next live class — time-sensitive, so it sits high with a one-tap gated join. */}
      <NextClassCard />

      {/* An open quiz is deadline-bound, so it sits with the class card rather
          than waiting to be found on the Quizzes page. */}
      <QuizDueCard />

      {/* Today's market brief — the daily signal, one tap from the dashboard. */}
      <MarketBriefCard />

      {/* Coach Panel — Next Best Action, PRI ring, mastery, wins & focus (PRD §6.4). */}
      <CoachPanel />

      {/* Batch leaderboard + badges (PRD §6.16). */}
      <LeaderboardCard />
    </div>
  );
}

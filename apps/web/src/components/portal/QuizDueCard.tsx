"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";

/**
 * Dashboard "quiz to sit" widget: the soonest quiz the student's trainer has
 * opened for their batch. A quiz used to arrive only as a WhatsApp/email magic
 * link, so a missed message meant a missed test — this puts it where they
 * already look. Hidden when nothing is outstanding.
 */

type QuizRow = {
  attempt_id: number;
  title: string;
  module: string | null;
  questions: number;
  minutes: number;
  status: "pending" | "in_progress" | "submitted" | "expired";
  due_at: string | null;
};

const TZ = "Asia/Kolkata";

function dueLabel(iso: string | null): string | null {
  if (!iso) return null;
  const days = Math.ceil((new Date(iso).getTime() - Date.now()) / 86_400_000);
  if (days < 0) return "Overdue";
  if (days === 0) return "Due today";
  if (days === 1) return "Due tomorrow";
  return `Due ${new Intl.DateTimeFormat("en-IN", { timeZone: TZ, day: "numeric", month: "short" }).format(new Date(iso))}`;
}

export function QuizDueCard() {
  const { data } = useQuery({
    queryKey: ["me", "quizzes"],
    queryFn: () => apiJson<{ data: QuizRow[] }>("/api/v1/me/quizzes"),
  });

  const open = (data?.data ?? []).filter((q) => q.status === "pending" || q.status === "in_progress");
  const next = open[0];

  if (!next) return null;

  const due = dueLabel(next.due_at);
  const overdue = due === "Overdue";

  return (
    <div className={`mt-6 rounded-2xl border p-5 ${overdue ? "border-warn/30 bg-warn/10" : "border-trust/30 bg-sky/40"}`}>
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="kicker text-trust">{next.status === "in_progress" ? "Quiz in progress" : "Quiz to sit"}</p>
          <p className="display mt-1 text-lg text-ink">{next.title}</p>
          <p className="mt-0.5 text-xs text-muted">
            {[next.module, `${next.questions} question${next.questions === 1 ? "" : "s"}`, `${next.minutes} min`]
              .filter(Boolean)
              .join(" · ")}
          </p>
        </div>
        {due && (
          <span className={`mono rounded-full px-2.5 py-0.5 text-[10px] uppercase tracking-widest ${overdue ? "bg-warn/15 text-warn" : "bg-white text-deep"}`}>
            {due}
          </span>
        )}
      </div>

      <div className="mt-4 flex flex-wrap items-center gap-3">
        <Link
          href={`/mcq/${next.attempt_id}`}
          className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5"
        >
          {next.status === "in_progress" ? "Resume quiz" : "Start quiz"}
        </Link>
        {open.length > 1 && (
          <Link href="/quizzes" className="text-sm font-semibold text-trust">
            {open.length - 1} more waiting →
          </Link>
        )}
      </div>
    </div>
  );
}

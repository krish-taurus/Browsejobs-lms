import { MonoCounter } from "@/components/motion/MonoCounter";
import type { EmployerRole, MemberRow } from "@/lib/employer";

/**
 * Divider classes for a 4-item strip that is 2×2 at mobile/tablet and 1×4 at
 * desktop — see SummaryStrip in the dashboard for why plain divide-x/y
 * misdraws this.
 */
function stripItemClasses(i: number): string {
  const mobileRight = i === 0 || i === 2;
  const mobileBottom = i === 0 || i === 1;
  const desktopRight = i !== 3;
  return [
    mobileRight ? "border-r" : "",
    mobileBottom ? "border-b" : "",
    "sm:border-b-0",
    desktopRight ? "sm:border-r" : "sm:border-r-0",
  ].join(" ");
}

/**
 * Team members / Owner / Recruiters / Hiring managers — all four counted
 * from the same real membership list, active members only (a pending
 * invite is not a member yet — see MembersList/InviteTeammateForm for that
 * separate, already-existing workflow).
 */
export function TeamSummary({ members }: { members: MemberRow[] | null }) {
  const counts = (members ?? []).reduce<Record<EmployerRole, number>>(
    (acc, m) => ({ ...acc, [m.role]: (acc[m.role] ?? 0) + 1 }),
    { owner: 0, recruiter: 0, hiring_manager: 0 },
  );

  const items = [
    { label: "Team members", value: members?.length ?? 0 },
    { label: counts.owner === 1 ? "Owner" : "Owners", value: counts.owner },
    { label: "Recruiters", value: counts.recruiter },
    { label: "Hiring managers", value: counts.hiring_manager },
  ];

  return (
    <div
      className="grid grid-cols-2 rounded-[var(--bj-dash-radius)] border bg-white sm:grid-cols-4"
      style={{ borderColor: "var(--bj-dash-border)" }}
    >
      {items.map((item, i) => (
        <div key={item.label} className={`p-5 sm:p-6 ${stripItemClasses(i)}`} style={{ borderColor: "var(--bj-dash-border)" }}>
          <span className="text-sm" style={{ color: "var(--bj-dash-muted)" }}>{item.label}</span>
          <p className="bj-dash-serif mt-2" style={{ fontSize: "clamp(2rem, 4vw, 2.75rem)", color: "var(--bj-dash-ink)" }}>
            {members === null ? (
              <span className="inline-block h-10 w-10 animate-pulse rounded-lg" style={{ background: "var(--bj-dash-soft)" }} />
            ) : (
              <MonoCounter value={item.value} className="bj-dash-serif" />
            )}
          </p>
        </div>
      ))}
    </div>
  );
}

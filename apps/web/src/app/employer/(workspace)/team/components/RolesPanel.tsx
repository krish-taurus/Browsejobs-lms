import { CrownIcon, DocumentIcon, UsersIcon } from "@/components/employer/icons";
import type { EmployerRole } from "@/lib/employer";
import { ROLE_HINTS, ROLE_LABELS } from "./roles";

const ROLE_ICON: Record<EmployerRole, typeof CrownIcon> = {
  owner: CrownIcon,
  recruiter: UsersIcon,
  hiring_manager: DocumentIcon,
};

const ROLE_ORDER: EmployerRole[] = ["owner", "recruiter", "hiring_manager"];

export function RolesPanel() {
  return (
    <div className="rounded-[var(--bj-dash-radius)] border bg-white p-5 sm:p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
      <h2 className="text-base font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Roles &amp; permissions</h2>

      <ul className="mt-4 divide-y" style={{ borderColor: "var(--bj-dash-border)" }}>
        {ROLE_ORDER.map((role) => {
          const Icon = ROLE_ICON[role];
          return (
            <li key={role} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0" style={{ borderColor: "var(--bj-dash-border)" }}>
              <span
                className="grid size-9 shrink-0 place-items-center rounded-xl"
                style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}
              >
                <Icon className="size-4" />
              </span>
              <div>
                <p className="text-sm font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{ROLE_LABELS[role]}</p>
                <p className="mt-0.5 text-sm" style={{ color: "var(--bj-dash-muted)" }}>{ROLE_HINTS[role]}</p>
              </div>
            </li>
          );
        })}
      </ul>

      <p className="mt-3 border-t pt-3 text-xs leading-relaxed" style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-muted)" }}>
        Owner access is assigned separately. Contact BrowseJobs after the teammate joins.
      </p>
    </div>
  );
}

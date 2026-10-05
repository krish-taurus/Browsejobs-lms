import { ShieldCheckIcon } from "@/components/employer/icons";

/**
 * These two claims are the same ones the page already made before this
 * redesign (team/page.tsx: "single-use signed link", "role changes are
 * written to the audit log", "expires in 7 days", "cannot be replayed") —
 * carried forward verbatim, not re-verified against the backend here, since
 * they were already the established, shipped copy.
 */
export function InvitationDetails() {
  return (
    <div
      className="relative overflow-hidden rounded-[var(--bj-dash-radius)] p-5 sm:p-6"
      style={{ background: "var(--bj-dash-soft)" }}
    >
      <div className="relative z-10 flex items-start gap-3">
        <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-white" style={{ color: "var(--bj-dash-primary)" }}>
          <ShieldCheckIcon className="size-4" />
        </span>
        <div>
          <h2 className="text-sm font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Invitation details</h2>
          <p className="mt-1.5 text-sm leading-relaxed" style={{ color: "var(--bj-dash-muted)" }}>
            Invite links can be accepted once and expire after 7 days. Role changes are recorded in the audit log.
          </p>
        </div>
      </div>

      {/* eslint-disable-next-line @next/next/no-img-element -- vector, decorative; see ReadinessCard for why not next/image */}
      <img
        src="/img/employer/botanical-accent.svg"
        alt=""
        aria-hidden
        width={120}
        height={157}
        className="pointer-events-none absolute -bottom-2 -right-2 opacity-70"
      />
    </div>
  );
}

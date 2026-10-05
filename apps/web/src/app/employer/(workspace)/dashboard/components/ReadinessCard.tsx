import { MonoCounter } from "@/components/motion/MonoCounter";

/**
 * "Pre-interviewed" readiness — graded applications over total applications,
 * both all-time and from the same source as the summary strip above. A zero
 * denominator renders "—" / "No applicants yet", never a fabricated 0% or
 * NaN (PRD-E honesty rule).
 */
export function ReadinessCard({
  gradedApplications,
  totalApplications,
}: {
  gradedApplications: number;
  totalApplications: number;
}) {
  const hasApplicants = totalApplications > 0;
  const pct = hasApplicants ? Math.round((gradedApplications / totalApplications) * 100) : null;

  return (
    <div
      className="relative flex h-full flex-col overflow-hidden rounded-[var(--bj-dash-radius)] border bg-white p-5 sm:p-6"
      style={{ borderColor: "var(--bj-dash-border)" }}
    >
      <div className="flex items-baseline justify-between">
        <h2 className="text-base font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Interview readiness</h2>
        <span className="text-xs" style={{ color: "var(--bj-dash-muted)" }}>All time</span>
      </div>

      <p className="bj-dash-serif mt-3" style={{ fontSize: "var(--bj-dash-metric-size)", color: "var(--bj-dash-ink)" }}>
        {hasApplicants ? <MonoCounter value={pct as number} suffix="%" className="bj-dash-serif" /> : "—"}
      </p>
      <span
        className="mt-1 inline-block w-fit rounded-full px-2.5 py-0.5 text-xs font-medium"
        style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}
      >
        Pre-interviewed
      </span>

      <p className="relative z-10 mt-4 max-w-[65%] text-sm leading-relaxed" style={{ color: "var(--bj-dash-muted)" }}>
        {hasApplicants
          ? `${gradedApplications} of ${totalApplications} applicants arrived with a graded interview.`
          : "No applicants yet — this fills in once candidates apply."}
      </p>

      <div className="relative z-10 mt-auto grid grid-cols-2 gap-4 border-t pt-4" style={{ borderColor: "var(--bj-dash-border)" }}>
        <div>
          <p className="text-xs" style={{ color: "var(--bj-dash-muted)" }}>Pre-interviewed</p>
          <p className="mt-0.5 text-xl font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{gradedApplications}</p>
        </div>
        <div>
          <p className="text-xs" style={{ color: "var(--bj-dash-muted)" }}>Total applicants</p>
          <p className="mt-0.5 text-xl font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{totalApplications}</p>
        </div>
      </div>

      {/* Purely decorative — kept out of the text column's width above (and
          aria-hidden/empty alt) so it never overlaps the numbers or reads as
          content. Plain <img>, not next/image: an already-vector SVG gets
          nothing from raster optimisation, and the optimizer 400s on SVG
          sources unless images.dangerouslyAllowSVG is set. */}
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src="/img/employer/botanical-accent.svg"
        alt=""
        aria-hidden
        width={140}
        height={183}
        className="pointer-events-none absolute -bottom-2 -right-2 opacity-80 sm:right-0"
      />
    </div>
  );
}

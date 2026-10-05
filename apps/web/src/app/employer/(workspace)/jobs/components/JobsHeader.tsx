import Link from "next/link";

export function JobsHeader() {
  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p className="font-mono text-[11px] font-semibold uppercase tracking-widest" style={{ color: "var(--bj-dash-primary)" }}>
          Your workspace
        </p>
        <h1 className="bj-dash-serif mt-1" style={{ fontSize: "var(--bj-dash-title-size)", color: "var(--bj-dash-ink)" }}>
          Your job descriptions
        </h1>
        <p className="mt-1 text-sm" style={{ color: "var(--bj-dash-muted)" }}>
          Manage your open roles and hiring criteria.
        </p>
      </div>

      {/* Same route, same permission gate as before this redesign — the
          button just moved and changed colour. */}
      <Link
        href="/employer/jobs/new"
        className="flex shrink-0 items-center gap-2 self-start rounded-full px-5 py-2.5 text-sm font-semibold text-white sm:self-auto"
        style={{ background: "var(--bj-dash-primary)" }}
      >
        Post a JD
        <span aria-hidden>→</span>
      </Link>
    </div>
  );
}

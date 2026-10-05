import Link from "next/link";
import { MonoCounter } from "@/components/motion/MonoCounter";
import { ArrowUpRightIcon } from "@/components/employer/icons";

export type SummaryItem = {
  label: string;
  value: number;
  /** Only shown when the number actually supports the claim — never invented. */
  scope: string;
  scopeTone?: "neutral" | "running" | "empty";
  href: string;
};

/**
 * Divider classes for a 4-item strip that is 2×2 at mobile/tablet and 1×4 at
 * desktop — plain `divide-x`/`divide-y` misdraws this (a grid's 3rd child
 * isn't its visual row-neighbour of the 2nd), so each of the 4 known
 * positions is spelled out directly instead.
 */
function stripItemClasses(i: number): string {
  const mobileRight = i === 0 || i === 2; // left column of each 2×2 row
  const mobileBottom = i === 0 || i === 1; // top row of the 2×2
  const desktopRight = i !== 3; // every column but the last, in a single row
  return [
    mobileRight ? "border-r" : "",
    mobileBottom ? "border-b" : "",
    "sm:border-b-0",
    desktopRight ? "sm:border-r" : "sm:border-r-0",
  ].join(" ");
}

/** One white strip, four equal compartments, fine vertical dividers. */
export function SummaryStrip({ items }: { items: SummaryItem[] }) {
  return (
    <div
      className="grid grid-cols-2 rounded-[var(--bj-dash-radius)] border bg-white sm:grid-cols-4"
      style={{ borderColor: "var(--bj-dash-border)" }}
    >
      {items.map((item, i) => (
        <Link
          key={item.label}
          href={item.href}
          className={`group p-5 transition-colors hover:bg-[var(--bj-dash-soft)]/40 sm:p-6 ${stripItemClasses(i)}`}
          style={{ borderColor: "var(--bj-dash-border)" }}
        >
          <span className="flex items-center gap-1.5 text-sm" style={{ color: "var(--bj-dash-muted)" }}>
            {item.label}
            <ArrowUpRightIcon className="size-3.5 text-[var(--bj-dash-muted)] transition-colors group-hover:text-[var(--bj-dash-primary)]" />
          </span>
          <p className="bj-dash-serif mt-2" style={{ fontSize: "clamp(2rem, 4vw, 2.75rem)", color: "var(--bj-dash-ink)" }}>
            <MonoCounter value={item.value} className="bj-dash-serif" />
          </p>
          <span className="mt-1 flex items-center gap-1.5 text-xs" style={{ color: "var(--bj-dash-muted)" }}>
            {item.scopeTone === "running" && <span className="size-1.5 rounded-full" style={{ background: "var(--bj-dash-primary)" }} />}
            {item.scope}
          </span>
        </Link>
      ))}
    </div>
  );
}

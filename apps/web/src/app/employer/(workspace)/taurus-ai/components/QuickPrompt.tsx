import Link from "next/link";
import type { ReactNode } from "react";

/** A quick-start row — either a real navigation link (candidate search, pipeline: no chat backend exists for either) or a button that focuses the composer (JD drafting: the one capability this page actually has). */
export function QuickPrompt({
  icon,
  title,
  description,
  href,
  onClick,
}: {
  icon: ReactNode;
  title: string;
  description: string;
  href?: string;
  onClick?: () => void;
}) {
  const inner = (
    <>
      <span
        className="grid size-10 shrink-0 place-items-center rounded-xl"
        style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}
      >
        {icon}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{title}</span>
        <span className="block truncate text-xs" style={{ color: "var(--bj-dash-muted)" }}>{description}</span>
      </span>
      <span aria-hidden style={{ color: "var(--bj-dash-muted)" }}>→</span>
    </>
  );

  const className = "flex w-full items-center gap-3 rounded-xl border px-3.5 py-3 text-left transition-colors hover:border-[var(--bj-dash-primary)] hover:bg-[var(--bj-dash-soft)]/40";
  const style = { borderColor: "var(--bj-dash-border)" };

  if (href) {
    return <Link href={href} className={className} style={style}>{inner}</Link>;
  }
  return (
    <button type="button" onClick={onClick} className={className} style={style}>
      {inner}
    </button>
  );
}

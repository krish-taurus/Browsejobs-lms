import type { ReactNode } from "react";

/** Teaching empty state — never a dead end (CLAUDE.md). */
export function EmptyState({
  title,
  body,
  action,
}: {
  title: string;
  body: string;
  action?: ReactNode;
}) {
  return (
    <div className="grid place-items-center rounded-2xl border border-dashed border-line bg-white px-6 py-16 text-center">
      <div className="grid h-12 w-12 place-items-center rounded-full bg-sky">
        <svg viewBox="0 0 24 24" className="h-6 w-6 text-trust" fill="none">
          <path
            d="M12 8v5m0 3h.01M12 3l9 16H3L12 3Z"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
      </div>
      <h3 className="display mt-4 text-lg text-ink">{title}</h3>
      <p className="mt-1 max-w-sm text-sm text-muted">{body}</p>
      {action && <div className="mt-5">{action}</div>}
    </div>
  );
}

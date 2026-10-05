export function Pagination({
  currentPage,
  lastPage,
  onChange,
  label,
}: {
  currentPage: number;
  lastPage: number;
  onChange: (page: number) => void;
  label: string;
}) {
  if (lastPage <= 1) return null;

  return (
    <nav aria-label={label} className="mt-4 flex items-center justify-center gap-3">
      <button
        type="button"
        onClick={() => onChange(currentPage - 1)}
        disabled={currentPage <= 1}
        className="rounded-full border px-3.5 py-1.5 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-40"
        style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
      >
        ← Prev
      </button>
      <span className="text-sm" style={{ color: "var(--bj-dash-muted)" }}>
        Page {currentPage} of {lastPage}
      </span>
      <button
        type="button"
        onClick={() => onChange(currentPage + 1)}
        disabled={currentPage >= lastPage}
        className="rounded-full border px-3.5 py-1.5 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-40"
        style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
      >
        Next →
      </button>
    </nav>
  );
}

export function TeamHeader() {
  return (
    <div>
      <p className="font-mono text-[11px] font-semibold uppercase tracking-widest" style={{ color: "var(--bj-dash-primary)" }}>
        Your workspace
      </p>
      <h1 className="bj-dash-serif mt-1" style={{ fontSize: "var(--bj-dash-title-size)", color: "var(--bj-dash-ink)" }}>
        Team overview
      </h1>
      <p className="mt-1 text-sm" style={{ color: "var(--bj-dash-muted)" }}>
        Manage members, roles and invitations.
      </p>
    </div>
  );
}

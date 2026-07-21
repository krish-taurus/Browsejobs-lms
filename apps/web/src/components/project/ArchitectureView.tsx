/**
 * Renders a structured system architecture (layers → components + data flows)
 * as styled boxes. Shared by the admin preview and the student project page so
 * both match the downloadable PDF. Pure presentation, no client state.
 */

export type Architecture = {
  overview?: string;
  layers?: { name: string; components: { name: string; role?: string }[] }[];
  flows?: { from: string; to: string; label?: string }[];
};

export function ArchitectureView({ architecture }: { architecture: Architecture | null | undefined }) {
  const layers = architecture?.layers ?? [];
  const flows = architecture?.flows ?? [];
  if (layers.length === 0) return null;

  return (
    <div>
      {architecture?.overview && <p className="text-sm text-ink2/80">{architecture.overview}</p>}

      <div className="mt-4 space-y-2">
        {layers.map((layer, i) => (
          <div key={i} className="overflow-hidden rounded-[14px] border border-line">
            <div className="mono bg-ink px-3 py-1.5 text-[10px] uppercase tracking-widest text-white">{layer.name}</div>
            <div className="flex flex-wrap gap-2 p-3">
              {layer.components.map((c, j) => (
                <div key={j} className="rounded-[10px] border border-line bg-paper px-3 py-2">
                  <p className="mono text-sm font-semibold text-ink">{c.name}</p>
                  {c.role && <p className="text-[11px] text-muted">{c.role}</p>}
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>

      {flows.length > 0 && (
        <div className="mt-4">
          <p className="mono text-[10px] uppercase tracking-widest text-muted">Data flow</p>
          <ul className="mt-2 space-y-1.5">
            {flows.map((f, i) => (
              <li key={i} className="flex flex-wrap items-center gap-2 text-sm text-ink">
                <span className="mono rounded bg-sky px-2 py-0.5 text-[12px] text-deep">{f.from}</span>
                <span className="text-trust">→</span>
                <span className="mono rounded bg-sky px-2 py-0.5 text-[12px] text-deep">{f.to}</span>
                {f.label && <span className="text-xs text-muted">· {f.label}</span>}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

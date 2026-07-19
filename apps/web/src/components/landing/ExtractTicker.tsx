/**
 * A quiet conveyor of interview topics gliding beneath the hero — the Syllabus
 * Engine's raw material made visible. Illustrative topics only (no stats, no
 * claims). Pure CSS translateX loop (pauses on hover); the reduced-motion
 * media query in globals.css freezes the track, so no JS branch is needed and
 * the DOM is identical either way (hydration-safe). Server component.
 */

const TOPICS = [
  "SQL window functions",
  "Spark partitioning",
  "Airflow DAG design",
  "dbt incremental models",
  "Kafka consumer groups",
  "Docker multi-stage builds",
  "Kubernetes probes",
  "Terraform state",
  "Python decorators",
  "Slowly changing dimensions",
  "CI/CD rollbacks",
  "Power BI DAX measures",
  "Data modelling trade-offs",
  "REST vs. event-driven",
];

function Row() {
  return (
    <>
      {TOPICS.map((t) => (
        <span key={t} className="flex shrink-0 items-center gap-3">
          <span className="mono whitespace-nowrap text-xs tracking-[0.08em] text-muted">{t}</span>
          <span aria-hidden className="h-1 w-1 shrink-0 rounded-full bg-trust/40" />
        </span>
      ))}
    </>
  );
}

export function ExtractTicker() {
  return (
    <section aria-label="Topics recently extracted from interviews" className="border-y border-line bg-white/60 py-4">
      <div className="mx-auto flex max-w-6xl items-center gap-6 px-5">
        <span className="mono shrink-0 text-[10px] font-semibold tracking-[0.18em] text-trust">
          FROM&nbsp;THE&nbsp;ENGINE
        </span>

        <div className="group relative flex-1 overflow-hidden ticker-mask">
          <div className="flex w-max items-center gap-3 ticker-track group-hover:[animation-play-state:paused]">
            <Row />
            {/* Second copy makes the -50% translate loop seamless. */}
            <Row />
          </div>
        </div>
      </div>
    </section>
  );
}

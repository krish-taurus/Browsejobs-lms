const ROLES = [
  "Data Engineer",
  "Analytics Engineer",
  "Backend Developer",
  "DevOps Engineer",
  "Data Analyst",
  "Platform Engineer",
  "SQL",
  "Python",
  "Airflow",
  "dbt",
  "Spark",
  "Kubernetes",
  "Terraform",
  "Snowflake",
  "Kafka",
];

/**
 * A conveyor of the roles and skills the engine tracks. Two identical copies
 * translated to -50% give a seamless loop with no JS; the mask fades both
 * ends so nothing pops in at the edge. Decorative, so the duplicate is
 * hidden from assistive tech and the strip is not a list of links.
 */
export function RoleTicker() {
  return (
    <div className="relative overflow-hidden border-y border-white/[0.07] py-4">
      <div className="ticker-mask">
        <div className="ticker-track flex w-max items-center gap-8">
          {[0, 1].map((copy) => (
            <div key={copy} className="flex items-center gap-8" aria-hidden={copy === 1}>
              {ROLES.map((r) => (
                <span key={r} className="flex items-center gap-8">
                  <span className="mono whitespace-nowrap text-[12px] uppercase tracking-[0.16em] text-white/30">
                    {r}
                  </span>
                  <span aria-hidden className="h-1 w-1 shrink-0 rounded-full bg-employer/60" />
                </span>
              ))}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

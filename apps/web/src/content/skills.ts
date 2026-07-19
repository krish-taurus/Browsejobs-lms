/**
 * Skill pages content — programmatic SEO layer over the skills the engine
 * already tracks (market-pulse cities + salary-page roles). Qualitative
 * direction only, consistent with spec §3. Each page cross-links roles,
 * cities, tracks and related skills for crawl depth.
 */

export type SkillPage = {
  slug: string;
  name: string;
  direction: "rising" | "steady";
  blurb: string;
  asked: string[];
  cities: string[];
  roles: { name: string; salarySlug: string | null }[];
  track: { name: string; href: string } | null;
  related: string[];
};

const kebab = (s: string) => s.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");

type Def = Omit<SkillPage, "slug">;

const DEFS: Def[] = [
  {
    name: "SQL", direction: "steady",
    blurb: "Still the single most-asked skill in Indian data interviews — every analytics, engineering and backend loop starts here.",
    asked: ["Window functions on real business data", "Query optimisation and explain plans", "Joins vs. subqueries under scale", "Slowly changing dimensions"],
    cities: ["Bengaluru", "Hyderabad", "Mumbai", "Delhi NCR"],
    roles: [{ name: "Data Engineer", salarySlug: "data-engineer-bengaluru" }, { name: "Data Analyst", salarySlug: "data-analyst-bengaluru" }],
    track: { name: "Data Analytics", href: "/courses/data-analytics" },
    related: ["python", "power-bi", "spark"],
  },
  {
    name: "Python", direction: "rising",
    blurb: "The default language of data and AI teams — interviews test working fluency, not textbook syntax.",
    asked: ["Decorators and generators in real code", "Pandas transformations on messy data", "API consumption and error handling", "Writing testable functions"],
    cities: ["Bengaluru", "Delhi NCR", "Pune", "Ahmedabad"],
    roles: [{ name: "Data Engineer", salarySlug: "data-engineer-bengaluru" }, { name: "Data Analyst", salarySlug: "data-analyst-delhi-ncr" }],
    track: { name: "Data Engineering", href: "/courses/data-engineering" },
    related: ["sql", "airflow", "spark"],
  },
  {
    name: "Spark", direction: "rising",
    blurb: "The workhorse of large-scale data processing — partitioning questions separate real practitioners from tutorial learners.",
    asked: ["Partitioning and shuffle behaviour", "Wide vs. narrow transformations", "Joins at scale and skew handling", "Structured streaming basics"],
    cities: ["Bengaluru", "Hyderabad", "Pune"],
    roles: [{ name: "Data Engineer", salarySlug: "data-engineer-bengaluru" }],
    track: { name: "Data Engineering", href: "/courses/data-engineering" },
    related: ["python", "kafka", "airflow"],
  },
  {
    name: "Airflow", direction: "rising",
    blurb: "Orchestration is where data engineering interviews get practical: DAG design says everything about how you think.",
    asked: ["DAG design and idempotent tasks", "Backfills and retries", "Sensor patterns and SLAs", "Migrating cron to Airflow"],
    cities: ["Bengaluru", "Chennai", "Pune"],
    roles: [{ name: "Data Engineer", salarySlug: "data-engineer-pune" }],
    track: { name: "Data Engineering", href: "/courses/data-engineering" },
    related: ["python", "spark", "dbt"],
  },
  {
    name: "Kubernetes", direction: "rising",
    blurb: "Every platform team's core interview topic — probes, resources and debugging pods under pressure.",
    asked: ["Liveness vs. readiness probes", "Debugging CrashLoopBackOff", "Resource requests and limits", "Rolling deployments and rollbacks"],
    cities: ["Bengaluru", "Pune", "Hyderabad"],
    roles: [{ name: "DevOps Engineer", salarySlug: "devops-engineer-bengaluru" }],
    track: { name: "DevOps & Cloud", href: "/courses/devops-cloud" },
    related: ["docker", "terraform", "aws"],
  },
  {
    name: "Docker", direction: "steady",
    blurb: "The entry ticket to every DevOps conversation — multi-stage builds and image hygiene come up in almost every loop.",
    asked: ["Multi-stage builds and image size", "Networking between containers", "Volumes and persistence", "Compose for local stacks"],
    cities: ["Bengaluru", "Pune", "Hyderabad"],
    roles: [{ name: "DevOps Engineer", salarySlug: "devops-engineer-pune" }],
    track: { name: "DevOps & Cloud", href: "/courses/devops-cloud" },
    related: ["kubernetes", "terraform", "aws"],
  },
  {
    name: "Terraform", direction: "rising",
    blurb: "Infrastructure-as-code went from nice-to-have to mandatory — state management questions are the filter.",
    asked: ["State files and locking", "Modules and reuse", "Plan/apply workflows in CI", "Importing existing infrastructure"],
    cities: ["Pune", "Bengaluru", "Hyderabad"],
    roles: [{ name: "DevOps Engineer", salarySlug: "devops-engineer-hyderabad" }],
    track: { name: "DevOps & Cloud", href: "/courses/devops-cloud" },
    related: ["kubernetes", "docker", "aws"],
  },
  {
    name: "AWS", direction: "steady",
    blurb: "The most common cloud in Indian job descriptions — interviews focus on the services you have actually run.",
    asked: ["Designing a data pipeline on AWS", "IAM roles vs. users", "S3 lifecycle and cost", "Glue vs. EMR trade-offs"],
    cities: ["Bengaluru", "Hyderabad", "Chennai", "Delhi NCR"],
    roles: [{ name: "Data Engineer", salarySlug: "data-engineer-hyderabad" }, { name: "DevOps Engineer", salarySlug: "devops-engineer-bengaluru" }],
    track: { name: "DevOps & Cloud", href: "/courses/devops-cloud" },
    related: ["terraform", "spark", "kubernetes"],
  },
  {
    name: "Power BI", direction: "steady",
    blurb: "The BI layer most Indian enterprises standardised on — DAX measures are where interviews decide.",
    asked: ["DAX measures vs. calculated columns", "Data modelling for dashboards", "Row-level security", "Refresh strategies"],
    cities: ["Mumbai", "Delhi NCR", "Bengaluru", "Kolkata"],
    roles: [{ name: "Data Analyst", salarySlug: "data-analyst-mumbai" }],
    track: { name: "Data Analytics", href: "/courses/data-analytics" },
    related: ["sql", "excel-bi", "python"],
  },
  {
    name: "dbt", direction: "rising",
    blurb: "The analytics-engineering standard — incremental models and testing discipline are the common interview probes.",
    asked: ["Incremental model strategies", "Tests and documentation", "Jinja macros in models", "Semantic layers"],
    cities: ["Bengaluru", "Pune"],
    roles: [{ name: "Data Engineer", salarySlug: "data-engineer-bengaluru" }],
    track: { name: "Data Engineering", href: "/courses/data-engineering" },
    related: ["sql", "airflow", "python"],
  },
  {
    name: "Kafka", direction: "rising",
    blurb: "Streaming questions moved from senior-only to standard — consumer groups and delivery guarantees lead the list.",
    asked: ["Consumer groups and rebalancing", "Exactly-once semantics", "Partitioning strategy", "Handling poison messages"],
    cities: ["Bengaluru", "Hyderabad"],
    roles: [{ name: "Data Engineer", salarySlug: "data-engineer-bengaluru" }],
    track: { name: "Data Engineering", href: "/courses/data-engineering" },
    related: ["spark", "python", "airflow"],
  },
  {
    name: "Excel + BI", direction: "steady",
    blurb: "Underrated and everywhere — analyst loops still open with Excel fluency before touching SQL.",
    asked: ["Lookups, pivots and what-if analysis", "Cleaning messy exports", "Building a one-page report", "When to graduate to BI tools"],
    cities: ["Mumbai", "Delhi NCR", "Kolkata", "Ahmedabad"],
    roles: [{ name: "Data Analyst", salarySlug: "data-analyst-mumbai" }],
    track: { name: "Data Analytics", href: "/courses/data-analytics" },
    related: ["power-bi", "sql", "python"],
  },
];

export const skillPages: SkillPage[] = DEFS.map((d) => ({ slug: kebab(d.name), ...d }));

export const getSkillPage = (slug: string) => skillPages.find((s) => s.slug === slug);

export const skillHref = (name: string): string | null => {
  const slug = kebab(name);
  return skillPages.some((s) => s.slug === slug) ? `/skills/${slug}` : null;
};

/**
 * Salary pages content — the curated benchmark dataset (mirrors the API's
 * SalaryBenchmarkSeeder; figures illustrative, LPA). Single source for the
 * programmatic /salaries pages: statically rendered for SEO, upgraded
 * client-side from /api/v1/salaries when live. Every figure renders with
 * the shared <Disclaimer/>.
 */

export type SalaryBand = { band: string; p25: number; p50: number; p75: number };

export type SalaryPage = {
  slug: string;
  role: string;
  city: string;
  bands: SalaryBand[];
  skills: string[];
  track: { name: string; href: string } | null;
  blurb: string;
};

const kebab = (s: string) => s.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");

type Row = { role: string; city: string; bands: SalaryBand[]; skills: string[]; track: SalaryPage["track"]; blurb: string };

const DE_SKILLS = ["Python", "SQL", "Spark", "Airflow", "AWS", "Databricks"];
const DA_SKILLS = ["SQL", "Excel", "Python", "Power BI", "DAX", "Statistics"];
const DEVOPS_SKILLS = ["Docker", "Kubernetes", "Terraform", "Jenkins", "AWS", "Linux"];
const JAVA_SKILLS = ["Java", "Spring Boot", "SQL", "React", "REST APIs", "Git"];
const QA_SKILLS = ["Selenium", "Java", "TestNG", "API testing", "CI/CD", "JIRA"];

const ROWS: Row[] = [
  { role: "Data Engineer", city: "Bengaluru", bands: [{ band: "fresher", p25: 6, p50: 8, p75: 11 }, { band: "1-3", p25: 10, p50: 14, p75: 20 }], skills: DE_SKILLS, track: { name: "Data Engineering", href: "/courses/data-engineering" }, blurb: "India's data-platform capital — GCCs, product companies and AI startups all hire pipeline builders here." },
  { role: "Data Engineer", city: "Hyderabad", bands: [{ band: "fresher", p25: 5.5, p50: 7.5, p75: 10 }, { band: "1-3", p25: 9, p50: 12.5, p75: 18 }], skills: DE_SKILLS, track: { name: "Data Engineering", href: "/courses/data-engineering" }, blurb: "Cloud and pharma-analytics builds keep Hyderabad's data teams growing quarter on quarter." },
  { role: "Data Engineer", city: "Pune", bands: [{ band: "fresher", p25: 5, p50: 7, p75: 9.5 }, { band: "1-3", p25: 8.5, p50: 12, p75: 17 }], skills: DE_SKILLS, track: { name: "Data Engineering", href: "/courses/data-engineering" }, blurb: "Pune's GCC wave is standing up lakehouse and platform teams at pace." },
  { role: "Data Analyst", city: "Bengaluru", bands: [{ band: "fresher", p25: 4.5, p50: 6, p75: 8.5 }, { band: "1-3", p25: 7, p50: 9.5, p75: 14 }], skills: DA_SKILLS, track: { name: "Data Analytics", href: "/courses/data-analytics" }, blurb: "Every funded startup's first data hire is an analyst who can own dashboards end to end." },
  { role: "Data Analyst", city: "Mumbai", bands: [{ band: "fresher", p25: 4.5, p50: 6, p75: 8 }, { band: "1-3", p25: 7, p50: 9, p75: 13 }], skills: DA_SKILLS, track: { name: "Data Analytics", href: "/courses/data-analytics" }, blurb: "BFSI and media analytics keep Mumbai's demand for SQL-strong analysts steady." },
  { role: "Data Analyst", city: "Delhi NCR", bands: [{ band: "fresher", p25: 4.5, p50: 6, p75: 8 }, { band: "1-3", p25: 7, p50: 9.5, p75: 13.5 }], skills: DA_SKILLS, track: { name: "Data Analytics", href: "/courses/data-analytics" }, blurb: "E-commerce and consumer-tech hubs in NCR run large analyst benches." },
  { role: "DevOps Engineer", city: "Bengaluru", bands: [{ band: "fresher", p25: 5, p50: 7, p75: 10 }, { band: "1-3", p25: 9, p50: 13, p75: 19 }], skills: DEVOPS_SKILLS, track: { name: "DevOps & Cloud", href: "/courses/devops-cloud" }, blurb: "Platform engineering is Bengaluru's fastest-compounding infra skill set." },
  { role: "DevOps Engineer", city: "Pune", bands: [{ band: "fresher", p25: 4.5, p50: 6.5, p75: 9 }, { band: "1-3", p25: 8, p50: 11.5, p75: 16.5 }], skills: DEVOPS_SKILLS, track: { name: "DevOps & Cloud", href: "/courses/devops-cloud" }, blurb: "Migration programmes at Pune GCCs need Kubernetes and Terraform hands." },
  { role: "DevOps Engineer", city: "Hyderabad", bands: [{ band: "fresher", p25: 4.5, p50: 6.5, p75: 9 }, { band: "1-3", p25: 8.5, p50: 12, p75: 17 }], skills: DEVOPS_SKILLS, track: { name: "DevOps & Cloud", href: "/courses/devops-cloud" }, blurb: "Hyderabad's cloud centres hire platform engineers across every major provider." },
  { role: "Java Full Stack Developer", city: "Bengaluru", bands: [{ band: "1-3", p25: 8, p50: 12, p75: 18 }], skills: JAVA_SKILLS, track: null, blurb: "Enterprise product teams keep Java + React the most listed stack in Bengaluru." },
  { role: "QA Automation Engineer", city: "Bengaluru", bands: [{ band: "fresher", p25: 4.5, p50: 6, p75: 8.5 }], skills: QA_SKILLS, track: null, blurb: "Automation-first QA roles replaced manual testing across Bengaluru product firms." },
];

export const salaryPages: SalaryPage[] = ROWS.map((r) => ({
  slug: `${kebab(r.role)}-${kebab(r.city)}`,
  ...r,
}));

export const getSalaryPage = (slug: string) => salaryPages.find((p) => p.slug === slug);

/** Other cities for the same role — the comparison strip + internal links. */
export const sameRolePages = (page: SalaryPage) =>
  salaryPages.filter((p) => p.role === page.role && p.slug !== page.slug);

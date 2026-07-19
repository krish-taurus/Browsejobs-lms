/**
 * Market Pulse — the hiring constellation's data contract.
 *
 * This snapshot is ILLUSTRATIVE: it names the cities and skills the syllabus
 * engine tracks, with qualitative direction only (no invented counts — spec
 * §3 forbids fabricated stats). When the backend market-intelligence API
 * lands, this module becomes its typed client and the component stays as-is.
 */

export type Trend = "rising" | "steady";

export type CityPulse = {
  key: string;
  name: string;
  /** Normalized position in the constellation (0–1, roughly geographic). */
  x: number;
  y: number;
  /** Relative signal weight (node size), 1–3. */
  weight: number;
  trend: Trend;
  /** Top skills the engine associates with this hub right now. */
  skills: { name: string; trend: Trend }[];
};

export const CITIES: CityPulse[] = [
  {
    key: "blr", name: "Bengaluru", x: 0.44, y: 0.74, weight: 3, trend: "rising",
    skills: [
      { name: "Data Engineering", trend: "rising" },
      { name: "Spark", trend: "rising" },
      { name: "Kubernetes", trend: "steady" },
      { name: "Python", trend: "steady" },
    ],
  },
  {
    key: "hyd", name: "Hyderabad", x: 0.47, y: 0.58, weight: 2, trend: "rising",
    skills: [
      { name: "Cloud (AWS/Azure)", trend: "rising" },
      { name: "SQL", trend: "steady" },
      { name: "DevOps", trend: "rising" },
      { name: "Power BI", trend: "steady" },
    ],
  },
  {
    key: "pun", name: "Pune", x: 0.30, y: 0.55, weight: 2, trend: "steady",
    skills: [
      { name: "DevOps", trend: "rising" },
      { name: "Java", trend: "steady" },
      { name: "Terraform", trend: "rising" },
      { name: "SQL", trend: "steady" },
    ],
  },
  {
    key: "mum", name: "Mumbai", x: 0.24, y: 0.48, weight: 2, trend: "steady",
    skills: [
      { name: "Data Analytics", trend: "rising" },
      { name: "Python", trend: "steady" },
      { name: "Excel + BI", trend: "steady" },
      { name: "SQL", trend: "rising" },
    ],
  },
  {
    key: "ncr", name: "Delhi NCR", x: 0.40, y: 0.16, weight: 2, trend: "rising",
    skills: [
      { name: "Data Analytics", trend: "rising" },
      { name: "Cloud (AWS)", trend: "steady" },
      { name: "Python", trend: "rising" },
      { name: "Power BI", trend: "steady" },
    ],
  },
  {
    key: "chn", name: "Chennai", x: 0.52, y: 0.80, weight: 2, trend: "steady",
    skills: [
      { name: "Backend (Python)", trend: "rising" },
      { name: "Airflow", trend: "steady" },
      { name: "SQL", trend: "steady" },
      { name: "AWS", trend: "rising" },
    ],
  },
  {
    key: "kol", name: "Kolkata", x: 0.72, y: 0.34, weight: 1, trend: "steady",
    skills: [
      { name: "SQL", trend: "steady" },
      { name: "Data Analytics", trend: "rising" },
      { name: "Python", trend: "steady" },
      { name: "Excel + BI", trend: "steady" },
    ],
  },
  {
    key: "amd", name: "Ahmedabad", x: 0.20, y: 0.34, weight: 1, trend: "steady",
    skills: [
      { name: "Data Analytics", trend: "steady" },
      { name: "Python", trend: "rising" },
      { name: "Power BI", trend: "steady" },
      { name: "SQL", trend: "steady" },
    ],
  },
];

/** Edges between hubs the engine correlates (indices into CITIES by key). */
export const EDGES: [string, string][] = [
  ["blr", "hyd"],
  ["blr", "chn"],
  ["blr", "pun"],
  ["hyd", "chn"],
  ["hyd", "ncr"],
  ["pun", "mum"],
  ["mum", "amd"],
  ["ncr", "mum"],
  ["ncr", "kol"],
  ["kol", "hyd"],
];

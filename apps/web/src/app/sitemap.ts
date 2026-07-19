import type { MetadataRoute } from "next";
import { salaryPages } from "@/content/salaries";
import { courses } from "@/content/landing";

const BASE = "https://browsejobs.ai";

export default function sitemap(): MetadataRoute.Sitemap {
  return [
    { url: BASE, priority: 1 },
    { url: `${BASE}/courses`, priority: 0.9 },
    ...courses.filter((c) => c.live).map((c) => ({ url: `${BASE}/courses/${c.slug}`, priority: 0.8 })),
    { url: `${BASE}/salaries`, priority: 0.8 },
    ...salaryPages.map((p) => ({ url: `${BASE}/salaries/${p.slug}`, priority: 0.7 })),
    { url: `${BASE}/reviews`, priority: 0.6 },
  ];
}

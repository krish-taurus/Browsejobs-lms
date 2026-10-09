import { expect, test } from "@playwright/test";
import { ANSWERS_UPDATED, answerPages, answerPath } from "../src/content/answers";
import { courseDetails } from "../src/content/courses";
import { PAGE_UPDATED } from "../src/content/last-modified";
import { SALARIES_UPDATED, salaryPages } from "../src/content/salaries";
import { seoMoneyLinks } from "../src/content/seo-nav";
import { SKILLS_UPDATED, skillPages } from "../src/content/skills";

const ORIGIN = "https://browsejobs.ai";

test("every sitemap url keeps its priority and an honest lastmod", async ({ request }) => {
  const response = await request.get("/sitemap.xml");
  expect(response.ok()).toBeTruthy();
  const xml = await response.text();
  const blocks = [...xml.matchAll(/<url>([\s\S]*?)<\/url>/g)].map((match) => match[1] ?? "");

  expect(blocks).toHaveLength(53);
  expect(xml).not.toContain("/courses/agentic-ai");

  const lastmod = (loc: string) => {
    const block = blocks.find((entry) => entry.includes(`<loc>${loc}</loc>`));
    expect(block, loc).toBeTruthy();
    expect(block).toMatch(/<priority>/);
    const found = block!.match(/<lastmod>([^<]+)<\/lastmod>/);
    expect(found?.[1], loc).toMatch(/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z)?$/);
    return found![1];
  };

  const starts = (path: string, date: string) => {
    expect(lastmod(path === "/" ? ORIGIN : `${ORIGIN}${path}`).startsWith(date), path).toBe(true);
  };

  for (const [path, date] of Object.entries(PAGE_UPDATED)) {
    starts(path, date);
  }
  for (const course of courseDetails.filter((item) => item.live)) {
    starts(`/courses/${course.slug}`, course.updatedAt);
  }
  starts("/salaries", SALARIES_UPDATED);
  for (const page of salaryPages) {
    starts(`/salaries/${page.slug}`, page.updatedAt ?? SALARIES_UPDATED);
  }
  starts("/skills", SKILLS_UPDATED);
  for (const page of skillPages) {
    starts(`/skills/${page.slug}`, page.updatedAt ?? SKILLS_UPDATED);
  }
  for (const page of seoMoneyLinks) {
    starts(page.path, page.updatedAt);
  }
  starts("/answers", ANSWERS_UPDATED);
  for (const page of answerPages) {
    starts(answerPath(page.slug), page.updatedAt ?? ANSWERS_UPDATED);
  }
});

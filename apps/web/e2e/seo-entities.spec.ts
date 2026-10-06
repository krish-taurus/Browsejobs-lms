import { expect, test } from "@playwright/test";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { courseDetails } from "../src/content/courses";
import {
  assertParsableJsonLd,
  courseNode,
  isoDuration,
  siteJsonLd,
} from "../src/lib/seo";

const INDEXNOW_KEY = "577828df9399c418e383248dd145664f";

test("sitewide entity graph parses and names the founder", () => {
  const parsed = assertParsableJsonLd(siteJsonLd());
  const nodes = parsed["@graph"] ?? [];
  const types = nodes.map((node) => node["@type"]);
  expect(types).toContain("EducationalOrganization");
  expect(types).toContain("WebSite");
  expect(types).toContain("Person");

  const raw = JSON.stringify(parsed);
  expect(raw).toContain('"name":"BrowseJobs"');
  expect(raw).toContain("Dr Krish Bharggav");
  expect(raw).toContain("https://browsejobs.ai/logo.svg");
  expect(raw).toContain("https://browsejobs.ai/founder");
  expect(raw).toContain('"@type":"NewsArticle"');
  expect(raw).not.toContain("logo.png");
  expect(raw).not.toContain("[Krish:");
  const graph = parsed["@graph"] ?? [];
  const org = graph.find((node) => node["@type"] === "EducationalOrganization") as { sameAs?: string[] } | undefined;
  const person = graph.find((node) => node["@type"] === "Person") as { sameAs?: string[]; url?: string } | undefined;
  expect(org?.sameAs).toEqual(["https://www.instagram.com/browsejobs.ai"]);
  expect(person?.url).toBe("https://browsejobs.ai/founder");
  expect(person?.sameAs).toEqual([
    "https://www.linkedin.com/in/dr-krish-bharggav-2072109a",
    "https://www.instagram.com/theofferletter6",
    "https://www.youtube.com/@theofferletter6",
  ]);
});

test("live course JSON-LD includes provider, fee, mode and duration", () => {
  const live = courseDetails.filter((course) => course.live);
  expect(live.map((course) => course.slug).sort()).toEqual([
    "data-analytics",
    "data-engineering",
    "devops-cloud",
    "python-backend",
  ]);

  for (const course of live) {
    const node = courseNode(course.slug);
    expect(node, course.slug).toBeTruthy();
    const parsed = assertParsableJsonLd({ "@context": "https://schema.org", "@graph": [node] });
    const raw = JSON.stringify(parsed);
    expect(raw).toContain('"@type":"Course"');
    expect(raw).toContain('"name":"BrowseJobs"');
    expect(raw).toContain('"price":"30000"');
    expect(raw).toContain('"priceCurrency":"INR"');
    expect(raw).toContain('"courseMode":"Online"');
    const iso = isoDuration(course.duration);
    expect(raw).toContain(iso ?? course.duration);
    if (course.tools.length === 0) expect(raw).not.toContain('"teaches"');
  }
});

test("homepage HTML includes organization, website and founder", async ({ page }) => {
  const response = await page.goto("/");
  expect(response?.ok()).toBeTruthy();
  const types = await collectTypes(page);
  expect(types.has("EducationalOrganization")).toBeTruthy();
  expect(types.has("WebSite")).toBeTruthy();
  expect(types.has("Person")).toBeTruthy();
  expect(types.has("Course")).toBeTruthy();
  const html = await page.content();
  expect(html).toContain("Dr Krish Bharggav");
  expect(html).toContain("https://browsejobs.ai/logo.svg");
});

for (const slug of ["data-engineering", "devops-cloud", "python-backend", "data-analytics"]) {
  test(`/courses/${slug} emits course and breadcrumb JSON-LD`, async ({ page }) => {
    const response = await page.goto(`/courses/${slug}`);
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", `https://browsejobs.ai/courses/${slug}`);
    const types = await collectTypes(page);
    expect(types.has("Course")).toBeTruthy();
    expect(types.has("BreadcrumbList")).toBeTruthy();
    const html = await page.content();
    expect(html).toContain('"courseMode":"Online"');
    expect(html).toContain('"priceCurrency":"INR"');
    expect(html).not.toContain("/courses/agentic-ai");
  });
}

test("/founder is indexable and links the press coverage", async ({ page, request }) => {
  const response = await page.goto("/founder");
  expect(response?.ok()).toBeTruthy();
  await expect(page.getByRole("heading", { level: 1 })).toHaveText("Dr Krish Bharggav");
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", "https://browsejobs.ai/founder");
  await expect(page.getByRole("heading", { name: "Dr Krish Bharggav in the news" })).toBeVisible();
  await expect(page.getByRole("link", { name: "Pioneer Edge" })).toHaveAttribute(
    "href",
    "https://pioneeredge.in/technology-skills-and-careers-how-dr-krish-bharggav-is-bridging-the-employment-gap/",
  );
  await expect(page.getByRole("link", { name: "The Offer Letter on YouTube" })).toBeVisible();
  const html = await page.content();
  expect(html).toContain('"@type":"NewsArticle"');
  expect(html).toContain("https://browsejobs.ai/founder");
  expect(html).not.toContain("[Krish:");
  const sitemap = await request.get("/sitemap.xml");
  expect(await sitemap.text()).toContain("https://browsejobs.ai/founder");
  const llms = await request.get("/llms.txt");
  expect(await llms.text()).toContain("https://browsejobs.ai/founder");
});

test("IndexNow key file is public and matches its name", async ({ request }) => {
  const response = await request.get(`/${INDEXNOW_KEY}.txt`);
  expect(response.ok()).toBeTruthy();
  expect((await response.text()).trim()).toBe(INDEXNOW_KEY);
  const onDisk = readFileSync(join(process.cwd(), "public", `${INDEXNOW_KEY}.txt`), "utf8").trim();
  expect(onDisk).toBe(INDEXNOW_KEY);
});

async function collectTypes(page: import("@playwright/test").Page): Promise<Set<string>> {
  const types = new Set<string>();
  for (const raw of await page.locator('script[type="application/ld+json"]').allTextContents()) {
    const data = JSON.parse(raw) as { "@context": string; "@graph"?: { "@type": string }[]; "@type"?: string };
    expect(data["@context"]).toBe("https://schema.org");
    if (data["@graph"]) {
      for (const node of data["@graph"]) types.add(node["@type"]);
    } else if (data["@type"]) {
      types.add(data["@type"]);
    }
  }
  return types;
}

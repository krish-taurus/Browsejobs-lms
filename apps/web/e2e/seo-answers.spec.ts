import { expect, test } from "@playwright/test";
import { answerPages, answerPath, priorityAnswerSlugs, wordCount } from "../src/content/answers";

const BANNED = ["HirePro", "InCruiter", "Talview", "TheHireHub", "Shifttotech", "Taurus", "guaranteed job", "100% placement"];

test("sitemap lists the answers hub and every answer", async ({ request }) => {
  const response = await request.get("/sitemap.xml");
  expect(response.ok()).toBeTruthy();
  const xml = await response.text();
  expect(xml).toContain("https://browsejobs.ai/answers");
  for (const page of answerPages) {
    expect(xml).toContain(`https://browsejobs.ai${answerPath(page.slug)}`);
  }
});

test("llms.txt lists the hub and every answer", async ({ request }) => {
  const response = await request.get("/llms.txt");
  expect(response.ok()).toBeTruthy();
  const text = await response.text();
  expect(text).toContain("https://browsejobs.ai/answers");
  for (const page of answerPages) {
    expect(text).toContain(`https://browsejobs.ai${answerPath(page.slug)}`);
  }
});

test("direct answers stay between 40 and 60 words", () => {
  for (const page of answerPages) {
    const count = wordCount(page.directAnswer);
    expect(count, page.slug).toBeGreaterThanOrEqual(40);
    expect(count, page.slug).toBeLessThanOrEqual(60);
  }
});

test("answers hub is indexable", async ({ page }) => {
  const response = await page.goto("/answers");
  expect(response?.ok()).toBeTruthy();
  await expect(page.getByRole("heading", { level: 1 })).toHaveText("Straight answers");
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", "https://browsejobs.ai/answers");
  for (const item of answerPages) {
    await expect(page.getByRole("link", { name: item.title }).first()).toBeVisible();
  }
  await expect(page.locator("#main").getByRole("link", { name: "Take the free AI interview", exact: true })).toBeVisible();
  const types = await jsonLdTypes(page);
  expect(types).toContain("FAQPage");
  expect(types).toContain("BreadcrumbList");
  expect(types).toContain("ItemList");
});

for (const item of answerPages) {
  test(`${answerPath(item.slug)} is an indexable answer`, async ({ page }) => {
    const response = await page.goto(answerPath(item.slug));
    expect(response?.ok()).toBeTruthy();
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(item.title);
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
      "href",
      `https://browsejobs.ai${answerPath(item.slug)}`,
    );
    await expect(page.locator("[data-direct-answer]")).toHaveText(item.directAnswer);
    await expect(page.locator("time")).toHaveAttribute("datetime", "2026-10-06");

    const html = await page.content();
    expect(html).not.toContain("/courses/agentic-ai");
    for (const banned of BANNED) {
      expect(html.toLowerCase()).not.toContain(banned.toLowerCase());
    }

    const scripts = await page.locator('script[type="application/ld+json"]').allTextContents();
    const types = new Set<string>();
    for (const raw of scripts) {
      expect(raw).not.toContain("[Krish:");
      const data = JSON.parse(raw) as { "@context": string; "@graph"?: { "@type": string }[] };
      expect(data["@context"]).toBe("https://schema.org");
      for (const node of data["@graph"] ?? []) types.add(node["@type"]);
    }
    expect(types.has("FAQPage")).toBeTruthy();
    expect(types.has("BreadcrumbList")).toBeTruthy();
    expect(types.has("WebPage")).toBeTruthy();
    if (priorityAnswerSlugs.has(item.slug)) {
      expect(item.title.length).toBeGreaterThan(10);
    }
  });
}

async function jsonLdTypes(page: import("@playwright/test").Page): Promise<Set<string>> {
  const types = new Set<string>();
  for (const raw of await page.locator('script[type="application/ld+json"]').allTextContents()) {
    const data = JSON.parse(raw) as { "@context": string; "@graph"?: { "@type": string }[] };
    expect(data["@context"]).toBe("https://schema.org");
    expect(raw).not.toContain("[Krish:");
    for (const node of data["@graph"] ?? []) types.add(node["@type"]);
  }
  return types;
}

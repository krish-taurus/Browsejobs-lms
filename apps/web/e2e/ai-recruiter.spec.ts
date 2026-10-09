import { expect, test } from "@playwright/test";

test("homepage has one heading, valid JSON-LD, and no agentic-ai link", async ({ page }) => {
  await page.goto("/");
  await expect(page.getByRole("heading", { level: 1 })).toHaveCount(1);
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", "https://browsejobs.ai");

  const scripts = page.locator('script[type="application/ld+json"]');
  const count = await scripts.count();
  expect(count).toBeGreaterThan(0);
  let sawFaq = false;
  for (let i = 0; i < count; i++) {
    const raw = await scripts.nth(i).textContent();
    const data = JSON.parse(raw ?? "") as { "@context"?: string; "@graph"?: { "@type"?: string }[] };
    expect(data["@context"]).toBe("https://schema.org");
    if (data["@graph"]?.some((node) => node["@type"] === "FAQPage")) sawFaq = true;
  }
  expect(sawFaq).toBe(true);
  for (let i = 0; i < count; i++) {
    const raw = await scripts.nth(i).textContent();
    const data = JSON.parse(raw ?? "") as { "@graph"?: Record<string, unknown>[]; "@type"?: string; review?: unknown; aggregateRating?: unknown };
    const nodes = data["@graph"] ?? [data];
    for (const node of nodes) {
      if (node["@type"] === "Course") {
        expect(node.review).toBeUndefined();
        expect(node.aggregateRating).toBeUndefined();
      }
    }
  }
  await expect(page.locator('a[href="/courses/agentic-ai"]')).toHaveCount(0);
});

test("homepage demo is labelled and links to the full floor", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/");
  const stage = page.locator("#recruiter");
  await stage.scrollIntoViewIfNeeded();
  await expect(stage.getByRole("heading", { level: 2, name: "Hiring? Meet your AI Recruiter." })).toBeVisible();
  await expect(stage.getByText(/Sample data/).first()).toBeVisible();
  await expect(stage.locator("video")).toHaveAttribute("src", "/media/taurus/taurus-hiring-story.mp4");
  const full = stage.getByRole("link", { name: "Watch the demo" });
  await expect(full).toHaveAttribute("href", "/employers/mission-control-demo");
  await page.goto("/employers/how-it-works");
  const stages = page.locator("#stages");
  await expect(stages).toContainText("Needs your approval");
  await expect(stages).toContainText("A person must always release the offer letter");
  await expect(stages.getByText("Not live")).toHaveCount(0);
  await expect(page.locator('a[href="/courses/agentic-ai"]')).toHaveCount(0);
});

test("employer demo plays the Taurus hiring story with sample data", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/employers/mission-control-demo");
  await expect(page.getByRole("heading", { level: 1, name: /BrowseJobs AI Recruiter demo/ })).toBeAttached();
  const floor = page.locator(".tc").first();
  await expect(floor.getByText("SAMPLE DATA")).toBeVisible();
  await expect(floor.locator(".tc-stage")).toHaveCount(10);
  await expect(floor.locator(".tc-phone")).toContainText("5 software developers in Bangalore");
  await expect(page.getByText(/not live/i)).toHaveCount(0);
  await floor.getByRole("button", { name: /Noir/ }).click();
  await expect(floor).toHaveAttribute("data-look", "noir");
});

test("employer demo stays readable on a phone", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/employers/mission-control-demo");
  await expect(page.locator(".tc-phone")).toBeVisible();
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
});

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
  await expect(page.locator('a[href="/courses/agentic-ai"]')).toHaveCount(0);
});

test("homepage demo is labelled and links to the full floor", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/");
  const stage = page.locator("#ai-recruiter");
  await stage.scrollIntoViewIfNeeded();
  await expect(stage.getByText("Demo data").first()).toBeVisible();
  await expect(stage.getByRole("heading", { level: 2, name: "Your AI Recruiter." })).toBeVisible();
  const full = stage.getByRole("link", { name: "Watch the demo" });
  await expect(full).toHaveAttribute("href", "/employers/mission-control-demo");
  await page.goto("/employers/how-it-works");
  const stages = page.locator("#stages");
  await expect(stages).toContainText("Needs your approval");
  await expect(stages).toContainText("A person must always release the offer letter");
  await expect(stages.getByText("Not live").first()).toBeVisible();
  await expect(page.locator('a[href="/courses/agentic-ai"]')).toHaveCount(0);
});

test("hiring floor shows demo data, stage counts, calls, and a candidate drawer", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/employers/mission-control-demo?at=52");

  await expect(page.getByText("Demo data").first()).toBeVisible();
  await expect(page.getByRole("heading", { level: 1, name: "BrowseJobs AI Recruiter" })).toBeVisible();
  await expect(page.getByText("Powered by Taurus AI").first()).toBeVisible();
  await expect(page.getByText("not live yet")).toBeVisible();

  const counts = page.getByRole("list", { name: "Stage counts" });
  await expect(counts).toBeVisible();
  await expect(counts.getByText("AI interview", { exact: true })).toBeVisible();
  await expect(counts.getByText("L1", { exact: true })).toBeVisible();
  await expect(counts.getByText("L2", { exact: true })).toBeVisible();
  await expect(counts.getByText("BGV", { exact: true })).toBeVisible();
  await expect(counts.getByText("Offer", { exact: true })).toBeVisible();
  await expect(counts.getByText("Needs your approval")).toBeVisible();
  await expect(page.getByText("Offers always need a human.").first()).toBeVisible();
  await page.getByRole("button", { name: "Ask before each step" }).click();
  await expect(page.getByRole("button", { name: "Autonomous on" })).toBeVisible();
  await expect(page.getByText("Offers always need a human.").first()).toBeVisible();
  await expect(page.getByText("Needs your approval").first()).toBeVisible();

  const calls = page.getByRole("region", { name: "Calls" });
  await expect(calls).toBeVisible();
  await expect(calls).toContainText("Sample Rohan Mehta");
  await expect(calls).toContainText("Interested");

  await page.getByRole("button", { name: "Open Sample Asha Iyer" }).click();
  const drawer = page.getByRole("dialog");
  await expect(drawer).toBeVisible();
  await expect(drawer).toContainText("Demo data");
  await expect(drawer).toContainText("Timeline");
  await expect(drawer).toContainText("Verified");
  await expect(drawer).toContainText("Fictional candidate");
  await page.keyboard.press("Escape");
  await expect(drawer).toHaveCount(0);
});

test("a typed role updates the demo brief", async ({ page }) => {
  await page.goto("/employers/mission-control-demo?at=52");
  await page.getByLabel("Tell the recruiter").fill("Hire 2 backend engineers in Hyderabad, 3-5 yrs, notice 15 days");
  await page.getByRole("button", { name: "Ask", exact: true }).click();
  await expect(page.locator(".job-line")).toHaveText("backend engineers · Hyderabad · 2 openings");
});

test("hiring floor stays readable on a phone", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/employers/mission-control-demo?at=52");
  await expect(page.getByText("Demo data").first()).toBeVisible();
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
  await page.getByRole("button", { name: "Candidates" }).click();
  await expect(page.getByRole("list", { name: "Stage counts" })).toBeVisible();
  await page.getByRole("button", { name: "Open Sample Asha Iyer" }).click();
  await expect(page.getByRole("dialog")).toContainText("Timeline");
});

test("talk answers a floor question from demo data", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/employers/mission-control-demo?at=52");
  await page.getByRole("button", { name: "Talk", exact: true }).click();
  const talk = page.getByRole("region", { name: "Talk to Recruiter" });
  await talk.getByLabel("Ask the recruiter").fill("how many are at L2?");
  await talk.getByRole("button", { name: "Ask", exact: true }).click();
  await expect(talk).toContainText("at L2");
  await talk.getByLabel("Ask the recruiter").fill("who's interested?");
  await talk.getByRole("button", { name: "Ask", exact: true }).click();
  await expect(talk).toContainText("Sample Asha Iyer");
  await talk.getByLabel("Ask the recruiter").fill("BGV status for Asha?");
  await talk.getByRole("button", { name: "Ask", exact: true }).click();
  await expect(talk).toContainText("Verified");
});

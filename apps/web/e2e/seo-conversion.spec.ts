import { expect, test } from "@playwright/test";

/**
 * /get-hired is the student page. Copy is server-rendered. Canonical is
 * absolute and self-referencing. Agentic AI must not be linked as a live course.
 * The employer money pages are covered by seo-money-pages.spec.ts.
 */

test("get-hired has a unique title, canonical and FAQ", async ({ page }) => {
  const response = await page.goto("/get-hired");
  expect(response?.ok()).toBeTruthy();

  await expect(page).toHaveTitle(/Get hired/i);
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", "https://browsejobs.ai/get-hired");
  await expect(page.getByRole("heading", { level: 1 })).toContainText(/clear the interview/i);
  await expect(page.getByText("Do I have to buy a course to find out if I can get a job?")).toBeVisible();

  const html = await page.content();
  expect(html).toContain('"@type":"FAQPage"');
  expect(html).not.toContain("reverse hiring");
});

test("get-hired links live courses and does not link a dead Agentic AI URL", async ({ page }) => {
  await page.goto("/get-hired");

  const tracks = page.locator("#tracks");
  await expect(tracks.locator('a[href="/courses/data-engineering"]')).toBeVisible();
  await expect(tracks.locator('a[href="/courses/devops-cloud"]')).toBeVisible();
  await expect(tracks.locator('a[href="/courses/python-backend"]')).toBeVisible();
  await expect(tracks.locator('a[href="/courses/data-analytics"]')).toBeVisible();
  await expect(page.locator('a[href="/courses/agentic-ai"]')).toHaveCount(0);

  await expect(page.getByRole("button", { name: "Start free AI interview" }).first()).toBeVisible();
  await expect(page.getByRole("link", { name: "WhatsApp us" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Three steps. Then you know." })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Two ways it can go" })).toBeVisible();
  await expect(page.getByRole("tab", { name: "HR queue" })).toBeVisible();
});

test("get-hired reduced motion keeps the three steps readable", async ({ browser }) => {
  const context = await browser.newContext({ reducedMotion: "reduce" });
  const page = await context.newPage();
  await page.goto("/get-hired");
  await expect(page.getByRole("heading", { level: 1 })).toContainText(/clear the interview/i);
  await expect(page.getByRole("heading", { name: "You take a free AI interview" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "You get a clear score and feedback" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Two ways it can go" })).toBeVisible();
  await expect(page.getByText("Cleared · sample").first()).toBeVisible();
  await context.close();
});

test("get-hired is in the sitemap", async ({ request }) => {
  const response = await request.get("/sitemap.xml");
  expect(response.ok()).toBeTruthy();
  expect(await response.text()).toContain("https://browsejobs.ai/get-hired");
});

test("hiring pages do not claim a dialler or Taurus as the product", async ({ page }) => {
  for (const path of ["/ai-hiring", "/ai-interview-platform", "/employers"]) {
    await page.goto(path);
    const html = await page.content();
    expect(html, path).not.toContain("AI caller");
    expect(html, path).not.toContain("Taurus");
  }
});

test("product pages keep the honest limit on proctoring capture", async ({ page }) => {
  for (const path of ["/ai-hiring", "/ai-interview-platform"]) {
    await page.goto(path);
    const html = await page.content();
    expect(html, path).not.toContain("proctored");
    expect(html, path).not.toContain("proctoring record");
  }
});

test("get-hired is linked from the homepage", async ({ page }) => {
  await page.goto("/");
  await page.getByRole("link", { name: "Get hired" }).first().click();
  await expect(page).toHaveURL(/\/get-hired$/);
});

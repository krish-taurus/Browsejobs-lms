import { expect, test } from "@playwright/test";

test("motion lab is noindex and absent from the sitemap", async ({ page, request }) => {
  const response = await page.goto("/dev/motion");
  expect(response?.ok()).toBeTruthy();
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", /noindex/);
  await expect(page.getByRole("heading", { level: 1 })).toContainText("Take a free AI interview");
  await expect(page.getByRole("navigation", { name: "Primary" })).toBeVisible();
  await expect(page.getByText("Sample data").first()).toBeVisible();
  await expect(page.locator('a[href="/courses/agentic-ai"]')).toHaveCount(0);

  const sitemap = await (await request.get("/sitemap.xml")).text();
  expect(sitemap).not.toContain("/dev/motion");
  const robots = await (await request.get("/robots.txt")).text();
  expect(robots).toContain("Disallow: /dev");
});

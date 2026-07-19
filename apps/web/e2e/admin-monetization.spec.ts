import { test, expect } from "@playwright/test";

/**
 * P2.8 DoD flow: staff signs in, reviews the seeded product catalog + monetization
 * settings, and opens the revenue dashboard.
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the MonetizationSeeder catalog.
 */
test.use({ baseURL: "http://localhost:3000" });

test("admin reviews monetization + revenue", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  // Monetization: settings + the seeded catalog.
  await page.getByRole("link", { name: "Monetization" }).first().click();
  await expect(page.getByRole("heading", { name: "Monetization" })).toBeVisible();
  await expect(page.getByText("Product catalog")).toBeVisible();
  await expect(page.getByText("Career+ (monthly)").first()).toBeVisible();
  await expect(page.getByText("Voice mock · 3-pack").first()).toBeVisible();

  // Revenue dashboard tiles.
  await page.getByRole("link", { name: "Revenue" }).first().click();
  await expect(page.getByRole("heading", { name: "Revenue by product" })).toBeVisible();
  await expect(page.getByText("Gross")).toBeVisible();
  await expect(page.getByText("Net", { exact: true })).toBeVisible();
});

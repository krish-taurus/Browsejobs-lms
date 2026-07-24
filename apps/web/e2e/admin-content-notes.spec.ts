import { test, expect } from "@playwright/test";

/**
 * P3.5b DoD flow (admin surface): a curriculum manager opens the Class notes list and drills
 * into a notes lesson whose transcript was seeded + approved (ContentSeeder), confirming the
 * editor renders. Transcript cleaning, notes generation and the KB feed are covered by Pest.
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the ContentSeeder-approved notes lesson.
 */
test.use({ baseURL: "http://localhost:3000" });

test("curriculum manager opens the class notes editor", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  await page.getByRole("link", { name: "Notes editor" }).first().click();
  await expect(page.getByRole("heading", { name: "Class notes" })).toBeVisible();

  // Open the seeded, approved notes lesson and confirm the editor renders.
  await page.locator('a[href^="/admin/content/"]').filter({ hasText: "approved" }).first().click();
  await expect(page.getByRole("button", { name: /Approve/ })).toBeVisible();
});

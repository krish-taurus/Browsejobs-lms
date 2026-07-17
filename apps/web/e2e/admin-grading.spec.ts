import { test, expect } from "@playwright/test";

/**
 * P3.4b DoD flow (admin surface): a trainer opens the grading queue and reviews a
 * submission's AI draft. The student submit flow + grade release are covered by the
 * Pest suite (they call the AI gateway / send messages).
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the AssignmentSeeder submission.
 */
test.use({ baseURL: "http://localhost:3000" });

test("trainer opens the grading queue", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  await page.getByRole("link", { name: "Grading" }).first().click();
  await expect(page.getByRole("heading", { name: "Grading" })).toBeVisible();

  // The released seed submission shows under the "released" tab.
  await page.getByRole("button", { name: "released" }).click();
  await expect(page.getByText("Build a REST endpoint").first()).toBeVisible();
});

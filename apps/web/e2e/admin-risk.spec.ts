import { test, expect } from "@playwright/test";

/**
 * P3.2 DoD flow: a counselor (admin has `manage-leads`) signs in and opens the
 * student Risk dashboard, which lists at-risk students sorted by dropout risk
 * with a rule-based intervention script they can copy.
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the ScoringSeeder-populated student_scores.
 */
test.use({ baseURL: "http://localhost:3000" });

test("counselor opens the student risk dashboard", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  await page.getByRole("link", { name: "Risk" }).first().click();
  await expect(page.getByRole("heading", { name: "Student risk" })).toBeVisible();

  // The summary tiles + at least one scored student with a copyable script.
  await expect(page.getByText("High risk")).toBeVisible();
  await expect(page.getByText("Students scored")).toBeVisible();
  await expect(page.getByRole("button", { name: "Copy script" }).first()).toBeVisible();
});

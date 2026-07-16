import { test, expect } from "@playwright/test";

/**
 * P3.3 DoD flow (admin surface): a curriculum manager opens the AI Tutor knowledge
 * base, rebuilds it from course/lab content, and authors a curated document. The
 * student chat itself calls the AI gateway and is covered by the Pest suite.
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the TutorSeeder-populated knowledge base.
 */
test.use({ baseURL: "http://localhost:3000" });

test("curriculum manager manages the tutor knowledge base", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  await page.getByRole("link", { name: "Tutor KB" }).first().click();
  await expect(page.getByRole("heading", { name: "Knowledge base" })).toBeVisible();

  // Rebuild the index from existing content.
  await page.getByRole("button", { name: "Rebuild from content" }).click();

  // Author a curated document.
  await page.getByPlaceholder("Title").fill("Exam week study plan");
  await page.getByPlaceholder("Content the tutor can draw on…").fill("Revise one module per day and attempt its lab.");
  await page.getByRole("button", { name: "Add document" }).click();

  await expect(page.getByText("Exam week study plan")).toBeVisible();
});

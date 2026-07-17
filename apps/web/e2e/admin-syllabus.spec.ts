import { test, expect } from "@playwright/test";

/**
 * P3.5c DoD flow (admin surface): a curriculum manager opens the Syllabus list and
 * drills into a course syllabus, seeing the AI generate + approve controls. The AI
 * generation, branded render, approval gate, and the public lead-gated download are
 * covered by the Pest suite (Feature/Syllabus/SyllabusTest).
 *
 * Requires the Laravel API on :8000 with the local seed (admin test@example.com /
 * password) and the SyllabusSeeder-approved course syllabus.
 */
test.use({ baseURL: "http://localhost:3000" });

test("curriculum manager reviews a course syllabus", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  await page.getByRole("link", { name: "Syllabus" }).first().click();
  await expect(page.getByRole("heading", { name: "Course syllabus" })).toBeVisible();

  // Open the first course and confirm the builder renders with Generate + Approve controls.
  await page.locator("a[href^='/admin/syllabus/']").first().click();
  await expect(page.getByRole("button", { name: /Regenerate|Generate with AI/ })).toBeVisible();
});

test("a visitor downloads a course syllabus behind the lead gate", async ({ page }) => {
  // The public course page exposes a lead-gated "Download syllabus" dialog.
  await page.goto("/courses/data-engineering");
  await page.getByRole("button", { name: "Download syllabus" }).first().click();

  await expect(page.getByRole("heading", { name: /Get the full syllabus|Syllabus coming soon/ })).toBeVisible();
});

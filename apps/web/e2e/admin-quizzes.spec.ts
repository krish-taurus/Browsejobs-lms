import { test, expect } from "@playwright/test";

/**
 * P3.4 DoD flow (admin surface): a curriculum manager opens the Quizzes list, drills
 * into a module quiz, and sees its questions. Generation calls the AI gateway and the
 * student MCQ runner is covered by the Pest suite.
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the QuizSeeder-approved module quiz.
 */
test.use({ baseURL: "http://localhost:3000" });

test("curriculum manager reviews a module quiz", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  await page.getByRole("link", { name: "Quizzes" }).first().click();
  await expect(page.getByRole("heading", { name: "Module quizzes" })).toBeVisible();

  // Open the seeded quiz lesson and confirm the manager renders with an Approve control.
  await page.getByText("Module 1 Check").first().click();
  await expect(page.getByRole("button", { name: /Approve/ })).toBeVisible();
});

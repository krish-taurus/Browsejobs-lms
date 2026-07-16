import { test, expect } from "@playwright/test";

/**
 * P2.5 DoD flow: staff signs in, completes the seeded bootcamp (converting its
 * attendee to the paid batch), and opens the funnel dashboard.
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the ConversionSeeder bootcamp (DE-202607-BC).
 */
test.use({ baseURL: "http://localhost:3000" });

test("admin completes a bootcamp and reviews the funnel", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  // Open the seeded bootcamp batch and complete it.
  await page.getByRole("link", { name: "Batches" }).first().click();
  await page.getByText("DE-202607-BC").click();
  page.once("dialog", (d) => d.accept());
  await page.getByRole("button", { name: /Complete bootcamp/ }).click();
  await expect(page.getByText(/attendee\(s\) converted/)).toBeVisible();

  // Funnel dashboard renders the conversion stages.
  await page.getByRole("link", { name: "Funnel" }).first().click();
  await expect(page.getByRole("heading", { name: "Conversion funnel" })).toBeVisible();
  await expect(page.getByText("By campaign")).toBeVisible();
});

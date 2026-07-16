import { test, expect } from "@playwright/test";

/**
 * P2.3 DoD flow: staff signs in and opens the Dunning dashboard, which shows the
 * seeded overdue + soft-blocked student and its aging.
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the PaymentsSeeder overdue demo (Ravi Kumar).
 */
test.use({ baseURL: "http://localhost:3000" });

test("admin reviews the dunning dashboard", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  await page.getByRole("link", { name: "Dunning" }).first().click();
  await expect(page.getByRole("heading", { name: "Dunning" })).toBeVisible();

  // Stat tiles + the seeded overdue student + its block badge.
  await expect(page.getByText("Expected collections")).toBeVisible();
  await expect(page.getByText("Active blocks")).toBeVisible();
  await expect(page.getByText("Ravi Kumar")).toBeVisible();
  await expect(page.getByText("soft block")).toBeVisible();
});

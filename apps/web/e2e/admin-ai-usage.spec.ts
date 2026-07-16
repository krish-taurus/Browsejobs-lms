import { test, expect } from "@playwright/test";

/**
 * P3.1 DoD flow: staff signs in and opens the AI usage + cost dashboard (the
 * read-only oversight of the gateway's spend).
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password).
 */
test.use({ baseURL: "http://localhost:3000" });

test("admin reviews AI usage", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  await page.getByRole("link", { name: "AI usage" }).first().click();
  await expect(page.getByRole("heading", { name: "AI usage & cost" })).toBeVisible();
  await expect(page.getByText("Calls today")).toBeVisible();
  await expect(page.getByText("Tokens today")).toBeVisible();
  await expect(page.getByText("Per-student daily token budget")).toBeVisible();
});

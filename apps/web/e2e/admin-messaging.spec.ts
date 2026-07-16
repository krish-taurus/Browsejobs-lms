import { test, expect } from "@playwright/test";

/**
 * P2.4 DoD flow: staff signs in, opens the messaging delivery log, and the
 * template manager (with the banned-phrase linter warning).
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the MessagingSeeder templates.
 */
test.use({ baseURL: "http://localhost:3000" });

test("admin reviews the message log and templates", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  await page.getByRole("link", { name: "Messaging" }).first().click();
  await expect(page.getByRole("heading", { name: "Delivery log" })).toBeVisible();

  // Open the template manager and trigger the banned-phrase linter.
  await page.getByRole("link", { name: "Templates" }).click();
  await expect(page.getByRole("heading", { name: "Message templates" })).toBeVisible();
  await page.getByRole("button", { name: /otp/ }).first().click();
  const editor = page.locator("textarea");
  await editor.fill("We offer a guaranteed job to everyone.");
  await expect(page.getByText(/Banned phrase/i)).toBeVisible();
});

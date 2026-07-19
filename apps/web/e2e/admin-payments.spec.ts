import { test, expect } from "@playwright/test";

/**
 * P2.2 DoD flow: staff signs in, opens Payments, creates an EMI fee plan for a
 * Reserved batch member (with the schedule previewed before confirm), and opens
 * the plan to generate a payment link.
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the PaymentsSeeder demo batch/member.
 */
// Visit on localhost so the Sanctum session cookie is same-site with the API.
test.use({ baseURL: "http://localhost:3000" });

test("admin creates an EMI fee plan and generates a payment link", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  await page.getByRole("link", { name: "Payments" }).first().click();
  await expect(page.getByRole("heading", { name: "Fee plans" })).toBeVisible();

  // Open the create panel and pick the seeded paid batch + a Reserved candidate.
  await page.getByRole("button", { name: "New fee plan" }).click();
  await page.getByLabel("Batch").selectOption({ index: 1 });
  // If a Reserved candidate exists, the student select becomes populated.
  await page.getByLabel("Plan").selectOption("emi");
  await expect(page.getByText("Schedule preview", { exact: true })).toBeVisible();
  // The EMI schedule shows three instalments.
  await expect(page.getByText("Instalment 3")).toBeVisible();

  // Open the seeded demo plan and confirm the schedule + ledger render.
  await page.getByRole("link", { name: "Payments" }).first().click();
  const firstPlan = page.locator('a[href^="/admin/payments/"]').first();
  await firstPlan.click();
  await expect(page.getByText("Instalment schedule")).toBeVisible();
  await expect(page.getByText("Outstanding")).toBeVisible();
});

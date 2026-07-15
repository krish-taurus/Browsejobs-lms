import { test, expect } from "@playwright/test";

/**
 * P1.4/P1.8 DoD flow: staff signs in, creates a batch (auto-numbered), and
 * adds a student to its roster.
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password, staff type, 2FA off).
 */
// The API lives on localhost:8000; the app must be visited on localhost too so
// the Sanctum session cookie is same-site (127.0.0.1 ↔ localhost is cross-site).
test.use({ baseURL: "http://localhost:3000" });

test("admin signs in, creates a batch, and rosters a student", async ({ page }) => {
  // Staff login
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  // Create a batch with a unique manual number (keeps reruns independent)
  const number = `E2E-${Date.now()}`;
  await page.getByRole("link", { name: "Batches" }).first().click();
  await expect(page.getByText("Create a batch")).toBeVisible();
  await page.getByLabel("Course").selectOption({ index: 1 });
  await page.getByLabel("Type").selectOption("bootcamp");
  await page.getByLabel("Number (blank = auto)").fill(number);
  await page.getByRole("button", { name: "Create batch" }).click();
  await expect(page.getByText(number)).toBeVisible();

  // Open it and add a student
  await page.getByText(number).click();
  await expect(page.getByText("Add a student")).toBeVisible();
  const suffix = String(Date.now()).slice(-8);
  await page.getByPlaceholder("Name", { exact: true }).fill("E2E Student");
  await page.getByPlaceholder("Phone", { exact: true }).fill(`+9190${suffix}`);
  await page.getByRole("button", { name: "Add to roster" }).click();

  await expect(page.getByText("E2E Student")).toBeVisible();
  await expect(page.getByText("reserved", { exact: false })).toBeVisible();
});

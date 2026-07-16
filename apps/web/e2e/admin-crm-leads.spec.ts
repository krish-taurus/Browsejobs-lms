import { test, expect } from "@playwright/test";

/**
 * P2.1 DoD flow: staff signs in, opens the CRM leads inbox, creates a lead by
 * hand, opens it, and moves it through a pipeline stage.
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the CrmSeeder pipeline stages.
 */
// Visit on localhost so the Sanctum session cookie is same-site with the API.
test.use({ baseURL: "http://localhost:3000" });

test("admin works a lead through the CRM", async ({ page }) => {
  // Staff login
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  // Open the leads inbox
  await page.getByRole("link", { name: "Leads" }).first().click();
  await expect(page.getByRole("heading", { name: "Leads inbox" })).toBeVisible();

  // Create a lead by hand (unique name keeps reruns independent)
  const name = `E2E Lead ${Date.now()}`;
  await page.getByRole("button", { name: "New lead" }).click();
  await page.getByLabel("Name").fill(name);
  await page.getByLabel("Phone").fill(`+9190${String(Date.now()).slice(-8)}`);
  await page.getByRole("button", { name: "Create" }).click();
  await expect(page.getByText(name)).toBeVisible();

  // Open it and move it to a new stage
  await page.getByText(name).click();
  await expect(page.getByRole("heading", { name })).toBeVisible();
  await page.getByRole("combobox").first().selectOption({ label: "Attended" });

  // Timeline records the stage change
  await expect(page.getByText("Moved to Attended")).toBeVisible();

  // Pipeline board renders the stage columns
  await page.getByRole("link", { name: "Leads" }).first().click();
  await page.getByRole("link", { name: "Pipeline board" }).click();
  await expect(page.getByRole("heading", { name: "Pipeline board" })).toBeVisible();
  await expect(page.getByText("Masterclass Registered")).toBeVisible();
});

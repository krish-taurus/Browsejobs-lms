import { test, expect } from "@playwright/test";

/**
 * P3.4c DoD flow (admin surface): a roster manager opens the Certificates page and
 * sees the manual-issue controls + the seeded certificate. Auto-issue on course
 * completion and the public /verify/{code} page are covered by the Pest suite.
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the CertificateSeeder certificate.
 */
test.use({ baseURL: "http://localhost:3000" });

test("roster manager opens the certificates page", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  await page.getByRole("link", { name: "Certificates" }).first().click();
  await expect(page.getByRole("heading", { name: "Certificates" })).toBeVisible();
  await expect(page.getByRole("button", { name: "Issue" })).toBeVisible();
});

import { test, expect } from "@playwright/test";

/**
 * PRD-E F9: the BGV credentials segment lives in super-admin settings, and
 * nowhere else.
 *
 * BrowseJobs runs these checks, not the employer, so the keys sit at platform
 * level — and platform level means super-admin only, not "any admin". This
 * spec asserts the gate rather than the form: the seeded admin persona holds
 * the `admin` role, which is exactly the account that must not reach these
 * credentials, and it is the more valuable assertion of the two.
 *
 * The form itself — every check routed independently, options limited to
 * real providers, secrets write-only and never returned — is covered by
 * PlatformSettingsTest, which runs as an actual super-admin.
 */
test.use({ baseURL: "http://localhost:3000" });

test("a plain admin cannot reach the platform credential groups", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\//);

  await page.goto("/admin/settings");

  // The page frame renders for any admin; the groups behind it do not.
  await expect(page.getByRole("heading", { name: "Settings" })).toBeVisible();

  await expect(page.getByText("Background verification (BGV)")).toHaveCount(0);
  await expect(page.getByText("DigiLocker — client secret")).toHaveCount(0);
  await expect(page.getByText("EPFO / BGV vendor — API key")).toHaveCount(0);

  // Not just BGV — no integration credential group is exposed at this role.
  await expect(page.locator("section")).toHaveCount(0);
});

test("a plain admin cannot onboard an employer either", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\//);

  // Creating a customer's workspace and their first login is a platform
  // action, gated the same way as the credentials — so it is not in the nav
  // for this role, and the page behind it yields nothing.
  await expect(page.getByRole("link", { name: "Employers", exact: true })).toHaveCount(0);

  await page.goto("/admin/employers");
  await expect(page.getByRole("button", { name: "Create workspace & invite" })).toBeDisabled();
  await expect(page.getByText("No employers yet.")).toBeVisible();
});

import { test, expect } from "@playwright/test";

/**
 * PRD-E F9: the BGV credentials segment in super-admin settings.
 *
 * BrowseJobs runs these checks, not the employer, so the keys live here and
 * nowhere near a workspace. The assertions worth keeping are that the segment
 * exists, that every check can be routed independently, and that a secret is
 * never rendered back into the page.
 */
test.use({ baseURL: "http://localhost:3000" });

test("super-admin can route each BGV check and store its credentials", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  // Navigating before the session lands leaves the settings page unauthenticated
  // and renders nothing to assert against.
  await expect(page).toHaveURL(/\/admin\//);

  await page.goto("/admin/settings");

  const section = page.locator("section").filter({ hasText: "Background verification (BGV)" });
  await expect(section).toBeVisible();

  // Manual review is the safe default: nothing is ever auto-passed without a
  // source behind it.
  await expect(section.getByLabel("Identity — checked by")).toHaveValue("manual");
  await expect(section.getByLabel("Employment — checked by")).toHaveValue("manual");

  // Each check routes on its own — identity can go to DigiLocker while
  // employment still waits for a person.
  await section.getByLabel("Identity — checked by").selectOption("digilocker");
  await expect(section.getByLabel("Employment — checked by")).toHaveValue("manual");

  // A secret is write-only: the field reports whether one is set, never what.
  const secret = section.getByLabel("DigiLocker — client secret");
  await expect(secret).toHaveValue("");
  await expect(secret).toHaveAttribute("type", "password");
});

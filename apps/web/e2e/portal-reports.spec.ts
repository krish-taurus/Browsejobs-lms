import { test, expect } from "@playwright/test";

/**
 * P3.5a: the weekly-report portal route is student-only and behind the auth guard.
 * Student sign-in is OTP-based (not automatable here), so this asserts the guard;
 * the report generation, delivery, and view payload are covered by the Pest suite.
 *
 * Requires the Next.js app on :3000.
 */
test.use({ baseURL: "http://localhost:3000" });

test("the reports route is guarded for signed-out visitors", async ({ page }) => {
  await page.goto("/reports");
  // The portal shell bounces unauthenticated visitors to the student sign-in.
  await expect(page).toHaveURL(/\/student/);
});

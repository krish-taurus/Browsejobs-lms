import { test, expect } from "@playwright/test";

/**
 * P3.5d-a: the support route (and the AI first-response layer on it) is student-only
 * and behind the auth guard.
 *
 * Student sign-in is OTP-based and not automatable here — the same limitation
 * portal-reports.spec.ts documents — so the deflect → accept and deflect →
 * raise-anyway flows are covered end-to-end by Pest
 * (tests/Feature/Support/TicketDeflectionTest.php) rather than in the browser.
 * This asserts the guard, which is the part a signed-out visitor can reach.
 *
 * Requires the Next.js app on :3000.
 */
test.use({ baseURL: "http://localhost:3000" });

test("the support route is guarded for signed-out visitors", async ({ page }) => {
  await page.goto("/support");
  // The portal shell bounces unauthenticated visitors to the student sign-in.
  await expect(page).toHaveURL(/\/student/);
});

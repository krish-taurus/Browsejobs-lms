import { test, expect } from "@playwright/test";

/**
 * P2.7 DoD flow: staff signs in, opens the Support Desk, works the seeded demo
 * ticket (reply → resolve), and confirms the student-context sidebar renders.
 *
 * Requires the Laravel API on :8000 with the local seed (admin
 * test@example.com / password) and the SupportSeeder demo ticket.
 */
test.use({ baseURL: "http://localhost:3000" });

test("staff works a support ticket to resolution", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  await page.getByRole("link", { name: "Support" }).first().click();
  await expect(page.getByRole("heading", { name: "Tickets" })).toBeVisible();

  // The seeded demo ticket is under "All".
  await page.getByRole("button", { name: "All" }).click();
  await page.getByText("Recordings will not play on my phone").first().click();

  // Workspace: student-context sidebar + reply + resolve.
  await expect(page.getByText("Student").first()).toBeVisible();
  await expect(page.getByText("Attendance")).toBeVisible();

  await page.getByPlaceholder("Reply to the student…").fill("Please clear your app cache and retry — let me know.");
  await page.getByRole("button", { name: "Send reply" }).click();

  // Move it to resolved via the status control.
  await page.locator("select").first().selectOption("resolved");
  await expect(page.locator("text=resolved").first()).toBeVisible();
});

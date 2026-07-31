import { test, expect } from "@playwright/test";

/**
 * PRD-E DoD flow: an employer signs in, lands on the command centre,
 * opens the seeded Data Engineer JD, inspects its auto-generated
 * JD-specific mock, and adds an automation rule.
 *
 * Requires the Laravel API on :8000 with the local seed (EmployerSeeder:
 * employer@example.com / password, Acme Technologies workspace with a
 * published Data Engineer JD and a ready mock).
 */
test.use({ baseURL: "http://localhost:3000" });

test("employer reviews a JD, its mock, and adds an automation rule", async ({ page }) => {
  await page.goto("/employer");
  await page.getByPlaceholder("Work email").fill("employer@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();

  // Command centre: stat band + pipeline pulse.
  await expect(page).toHaveURL(/\/employer\/dashboard/);
  await expect(page.getByRole("heading", { name: "Acme Technologies" })).toBeVisible();
  await expect(page.getByText("Active JDs")).toBeVisible();
  await expect(page.getByText("Pipeline pulse")).toBeVisible();
  await expect(page.getByRole("heading", { name: "Live per JD" })).toBeVisible();

  // Jobs list → the seeded published JD.
  await page.getByRole("link", { name: "Jobs" }).first().click();
  await expect(page.getByRole("heading", { name: "Your job descriptions" })).toBeVisible();
  await page.getByRole("link", { name: /Data Engineer/ }).first().click();
  await expect(page.getByRole("heading", { name: "Data Engineer" })).toBeVisible();

  // The JD mock generated at publish time: rubric weights + questions.
  await page.getByRole("tab", { name: "JD mock" }).click();
  await expect(page.getByText("Grading rubric")).toBeVisible();
  await expect(page.getByText("Technical depth").first()).toBeVisible();
  await expect(page.getByText(/Asked of every applicant/i)).toBeVisible();

  // Automation: auto-shortlist at 70%.
  await page.getByRole("tab", { name: "Automation" }).click();
  await expect(page.getByText(/Automation can advance or park candidates/)).toBeVisible();
  await page.getByRole("button", { name: "Add rule" }).click();
  await expect(page.getByText(/Job interview ≥ \d+%/).first()).toBeVisible();
});

test("command palette jumps to a JD", async ({ page }) => {
  await page.goto("/employer");
  await page.getByPlaceholder("Work email").fill("employer@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/employer\/dashboard/);

  await page.keyboard.press("ControlOrMeta+k");
  const palette = page.getByRole("dialog", { name: "Command palette" });
  await expect(palette).toBeVisible();
  await palette.getByPlaceholder("Jump to a screen or JD…").fill("Data");
  await page.keyboard.press("Enter");

  await expect(page).toHaveURL(/\/employer\/jobs\/\d+/);
});

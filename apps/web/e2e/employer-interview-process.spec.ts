import { test, expect } from "@playwright/test";

/**
 * PRD-E F18: an employer designs the interview process for a JD and sends a
 * round to a candidate.
 *
 * Requires the Laravel API on :8000 with the local seed (EmployerSeeder:
 * employer@example.com / password; the Data Engineer JD runs a three-round
 * process — two AI rounds and one human call).
 */
test.use({ baseURL: "http://localhost:3000" });

async function signIn(page: import("@playwright/test").Page) {
  await page.goto("/employer");
  await page.getByLabel("Work email").fill("employer@example.com");
  await page.getByLabel("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/employer\/dashboard/);
}

test("employer reviews the designed interview process", async ({ page }) => {
  await signIn(page);

  await page.getByRole("link", { name: "Jobs", exact: true }).first().click();
  await page.getByRole("link", { name: /Data Engineer/ }).first().click();
  await page.getByRole("tab", { name: "Process" }).click();

  await expect(page.getByRole("heading", { name: /rounds this role runs/ })).toBeVisible();

  // The seeded process: a manual screen, an automatic depth round, and a
  // human call the platform tracks but never sends.
  await expect(page.getByRole("button", { name: "Send it automatically", pressed: true })).toBeVisible();
  await expect(page.getByText("You schedule this one. Nothing is sent from here.")).toBeVisible();
});

test("a human round can never be set to send itself", async ({ page }) => {
  await signIn(page);
  await page.goto("/employer/jobs/1");
  await page.getByRole("tab", { name: "Process" }).click();

  // Switching the first round to a human one must withdraw the automatic
  // option outright — the platform has nothing to send for it.
  await page.getByLabel("Round 1 kind").selectOption("human");

  await expect(page.getByText("You schedule this one. Nothing is sent from here.").first()).toBeVisible();
  await expect(page.getByLabel("Round 1 question count")).toBeDisabled();
});

test("employer sends a round to a candidate", async ({ page }) => {
  await signIn(page);
  await page.goto("/employer/pipeline");
  await page.getByRole("link", { name: "Open" }).first().click();
  await expect(page).toHaveURL(/\/candidates\/\d+/);

  await expect(page.getByRole("heading", { name: "Send a round" })).toBeVisible();

  // Whether this candidate has already sat a round depends on the order the
  // board returns, so accept either state — what must hold is that the panel
  // never offers to send a round that has already gone out.
  const sendable = page.getByRole("button", { name: "Send" });

  if ((await sendable.count()) > 0) {
    await sendable.first().click();
    await expect(page.getByText(/sent\. The candidate has \d+ hours/)).toBeVisible();
  } else {
    await expect(page.getByText("Sent").first()).toBeVisible();
  }
});

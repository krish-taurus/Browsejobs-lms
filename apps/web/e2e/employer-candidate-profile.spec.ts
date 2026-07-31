import { test, expect } from "@playwright/test";

/**
 * PRD-E F17: an employer opens the full profile behind an application.
 *
 * The assertions that matter here are the disclosure rules, not the layout.
 * A regression that leaks a phone number before Shortlisted, or that quietly
 * stops saying proctoring was never captured, is the kind of thing that
 * looks fine in a screenshot and is wrong in substance.
 *
 * Requires the Laravel API on :8000 with the local seed (EmployerSeeder:
 * employer@example.com / password; Ananya Iyer verified and at L1, Sneha
 * Nair graded with a failed employment check).
 */
test.use({ baseURL: "http://localhost:3000" });

async function signIn(page: import("@playwright/test").Page) {
  await page.goto("/employer");
  await page.getByLabel("Work email").fill("employer@example.com");
  await page.getByLabel("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/employer\/dashboard/);
}

test("employer opens a candidate's full profile from the JD", async ({ page }) => {
  await signIn(page);

  await page.getByRole("link", { name: "Jobs", exact: true }).first().click();
  await page.getByRole("link", { name: /Data Engineer/ }).first().click();
  await page.getByRole("link", { name: "Full profile" }).first().click();

  await expect(page).toHaveURL(/\/employer\/jobs\/\d+\/candidates\/\d+/);

  // The verified stamp is earned from the checks, not decorative.
  await expect(page.getByRole("heading", { name: "Ananya Iyer" })).toBeVisible();
  await expect(page.getByText("Verified candidate")).toBeVisible();
  await expect(page.getByRole("heading", { name: "What has actually been checked" })).toBeVisible();
  await expect(page.getByText("Employment history")).toBeVisible();

  // Mock analysis: per-dimension scores and both sides of the read.
  await expect(page.getByRole("heading", { name: /dimension by dimension/ })).toBeVisible();
  await expect(page.getByText("Technical depth")).toBeVisible();
  await expect(page.getByText("What went well")).toBeVisible();
  await expect(page.getByText("Where they were weaker")).toBeVisible();

  // The honest gap: no proctoring signals are recorded, and the page says so
  // rather than letting silence read as a clean session.
  await expect(page.getByText(/Proctoring signals were not captured/)).toBeVisible();
});

test("contact details stay hidden until the candidate is shortlisted", async ({ page }) => {
  await signIn(page);

  await page.getByRole("link", { name: "Jobs", exact: true }).first().click();
  await page.getByRole("link", { name: /Data Engineer/ }).first().click();

  // Sneha Nair is seeded at Graded and Rahul Verma at Shortlisted, so the
  // pair proves the gate opens rather than that it is simply always shut.
  // Scoped by the card wrapper because both rows carry a "Full profile" link.
  const card = (name: string) =>
    page.locator("div.rounded-3xl").filter({ hasText: name }).last();

  await card("Sneha Nair").getByRole("link", { name: "Full profile" }).click();
  await expect(page.getByRole("heading", { name: "Sneha Nair" })).toBeVisible();
  await expect(page.getByText("Contact details unlock at Shortlisted")).toBeVisible();
  await expect(page.getByText("sneha.nair@example.com")).toHaveCount(0);
  await expect(page.getByText("Not fully verified")).toBeVisible();

  await page.goBack();
  await card("Rahul Verma").getByRole("link", { name: "Full profile" }).click();
  await expect(page.getByRole("heading", { name: "Rahul Verma" })).toBeVisible();
  await expect(page.getByText("rahul.verma@example.com")).toBeVisible();
});

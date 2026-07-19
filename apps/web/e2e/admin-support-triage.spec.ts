import { test, expect } from "@playwright/test";

/**
 * P3.5d-b: staff see the AI triage signals on a ticket, the queue is ordered so an
 * AI-raised priority actually moves, and the raise can be undone.
 *
 * Uses the seeded angry payments ticket ("My EMI was debited twice"), which
 * SupportCorpusSeeder triages to angry/high with the raise flag set — so this needs no
 * live model. The "Draft with AI" button is deliberately not exercised here: it needs a
 * real provider key, and Pest covers it against the faked transport.
 *
 * Requires the Laravel API on :8000 with the local seed (admin test@example.com /
 * password) and the Next.js app on :3000.
 */
test.use({ baseURL: "http://localhost:3000" });

test("staff see AI triage on a ticket and can undo the priority raise", async ({ page }) => {
  await page.goto("/admin");
  await page.getByPlaceholder("Work email").fill("test@example.com");
  await page.getByPlaceholder("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/admin\/curriculum/);

  await page.getByRole("link", { name: "Support" }).first().click();
  await expect(page.getByRole("heading", { name: "Tickets", exact: true })).toBeVisible();
  await page.getByRole("button", { name: "All" }).click();

  // The queue explains itself rather than silently reordering.
  const angry = page.getByText("My EMI was debited twice").first();
  await expect(angry).toBeVisible();
  await expect(page.getByText("AI raised to high").first()).toBeVisible();

  await angry.click();

  // The triage panel: tone, and an honest statement of what it did not do.
  await expect(page.getByText("AI triage")).toBeVisible();
  await expect(page.getByText("sounds angry")).toBeVisible();
  await expect(page.getByText("AI raised this to")).toBeVisible();
  await expect(page.getByText(/Nothing here routed, replied, or resolved/)).toBeVisible();

  // A human puts it back; the panel's raise notice goes away.
  await page.getByRole("combobox").filter({ hasText: "Put it back to…" }).selectOption("normal");
  await expect(page.getByText("AI raised this to")).toBeHidden();
});

import { expect, test } from "@playwright/test";

/**
 * /thank-you — the shared confirmation page every public lead form lands on.
 * Covers the course recap, the per-lead-type next steps, the privacy rule that
 * no personal data reaches the URL, and the fallback for a bare visit.
 */

test("shows the requested course and the masterclass next steps", async ({ page }) => {
  await page.goto("/thank-you?type=masterclass&course=data-engineering");

  await expect(page.getByRole("heading", { level: 1 })).toContainText("masterclass seat");

  // The course recap is driven by the ?course slug.
  await expect(page.getByRole("heading", { name: "Data Engineering" })).toBeVisible();
  await expect(page.getByText("6 months").first()).toBeVisible();
  await expect(page.getByRole("link", { name: /See the full syllabus/ })).toHaveAttribute(
    "href",
    "/courses/data-engineering",
  );

  // Next steps are ordered and specific to this lead type.
  await expect(page.getByText("You get the joining link")).toBeVisible();
  await expect(page.getByText("You attend, then decide")).toBeVisible();
});

test("varies the next steps by lead type", async ({ page }) => {
  await page.goto("/thank-you?type=counselling");
  await expect(page.getByRole("heading", { level: 1 })).toContainText("counselling call");
  await expect(page.getByText("You get the written report")).toBeVisible();

  await page.goto("/thank-you?type=employer");
  await expect(page.getByRole("heading", { level: 1 })).toContainText("hiring enquiry");
  await expect(page.getByText("Commercials in writing")).toBeVisible();

  // Waitlist programs have no brochure content, so the page falls back to the
  // card list rather than showing the person nothing about what they asked for.
  await page.goto("/thank-you?type=waitlist&course=agentic-ai");
  await expect(page.getByRole("heading", { level: 1 })).toContainText("on the list");
  await expect(page.getByRole("heading", { name: "Agentic AI" })).toBeVisible();
  // A not-yet-open program is labelled rather than sold as available.
  await expect(page.getByText("Opening soon")).toBeVisible();
  await expect(page.getByRole("link", { name: /See the full syllabus/ })).toHaveCount(0);
});

test("falls back gracefully with no params or an unknown type", async ({ page }) => {
  for (const url of ["/thank-you", "/thank-you?type=nonsense&course=nonsense"]) {
    const res = await page.goto(url);
    expect(res?.ok()).toBeTruthy();
    await expect(page.getByRole("heading", { level: 1 })).toContainText("we have your message");
    // No course block when the slug doesn't resolve.
    await expect(page.getByText("What you asked about")).toHaveCount(0);
  }
});

test("is excluded from search and carries no personal data in the URL", async ({ page }) => {
  await page.goto("/thank-you?type=masterclass&course=data-engineering");

  await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", /noindex/);
  expect(page.url()).not.toMatch(/name=|phone=|email=/);
});

test("the lead modal redirects here on submit", async ({ page }) => {
  await page.route("**/api/v1/leads", (route) =>
    route.fulfill({ status: 201, contentType: "application/json", body: JSON.stringify({ status: "received", id: 1 }) }),
  );
  await page.route("**/sanctum/csrf-cookie", (route) => route.fulfill({ status: 204, body: "" }));

  await page.goto("/courses/data-engineering");
  await page.getByRole("button", { name: /Book the free masterclass/i }).first().click();

  const modal = page.getByRole("dialog");
  await expect(modal).toBeVisible();
  await modal.getByPlaceholder("Your name").fill("Asha Rao");
  await modal.getByPlaceholder("WhatsApp number").fill("+919000000001");
  await modal.getByRole("checkbox").check();
  await modal.getByRole("button", { name: /Book my free seat/i }).click();

  await expect(page).toHaveURL(/\/thank-you\?type=masterclass/);
  await expect(page.getByRole("heading", { level: 1 })).toContainText("masterclass seat");
});

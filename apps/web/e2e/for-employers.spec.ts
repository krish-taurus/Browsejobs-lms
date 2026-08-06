import { test, expect } from "@playwright/test";

/**
 * The employer landing page's user flow: arrive from the homepage band, walk
 * the step-by-step tabs, read the sample report, and reach the enquiry form.
 *
 * The lead POST is stubbed rather than sent — the DoD forbids hitting real
 * endpoints from tests, and this asserts the payload the form builds, which
 * is the part that can silently regress.
 *
 * Requires the Next app on :3000.
 */
test.use({ baseURL: "http://localhost:3000" });

test("an employer walks the landing page and sends an enquiry", async ({ page }) => {
  await page.goto("/for-employers");

  await expect(
    page.getByRole("heading", { level: 1, name: /already interviewed/i }),
  ).toBeVisible();

  // The walkthrough is a tablist: the first step is selected, and choosing a
  // later step swaps the product frame.
  const tablist = page.getByRole("tablist", { name: /step by step/i });
  await expect(tablist.getByRole("tab", { selected: true })).toContainText("Open a workspace");

  await tablist.getByRole("tab", { name: /Design the assessment/ }).click();
  await expect(page.getByText("Assessment designer")).toBeVisible();

  // Arrow keys move between steps without a mouse.
  await page.keyboard.press("ArrowDown");
  await expect(tablist.getByRole("tab", { selected: true })).toContainText("Design the rounds");

  // The sample report shows the states a sales page would hide.
  await expect(page.getByText("Contact details unlock at Shortlisted.")).toBeVisible();
  await expect(page.getByText(/Proctoring signals were not captured/)).toBeVisible();
  await expect(page.getByText("Sample · fictional candidate").first()).toBeVisible();

  // The honest-limits column is on the page, not buried.
  await expect(page.getByText("Where it falls short")).toBeVisible();

  // Enquiry: the CTA scrolls to the form, and the form posts an employer lead.
  const posted: Record<string, unknown>[] = [];
  await page.route("**/api/v1/leads", async (route) => {
    posted.push(route.request().postDataJSON());
    await route.fulfill({ status: 201, json: { status: "received", id: 1 } });
  });

  await page.getByRole("button", { name: "Talk to us about hiring" }).click();
  await page.getByLabel("Company").fill("Meridian Logistics");
  await page.getByLabel("Your name").fill("Divya Prasad");
  await page.getByLabel("Work email").fill("divya@meridian.test");
  await page.getByLabel("Phone").fill("+919000000001");
  await page.getByLabel("Roles you are hiring for").fill("Data engineer");
  await page.getByRole("checkbox").check();
  await page.getByRole("button", { name: "Send the enquiry" }).click();

  await expect(page.getByRole("heading", { name: "We have it." })).toBeVisible();
  expect(posted).toHaveLength(1);
  expect(posted[0]).toMatchObject({
    lead_type: "employer",
    name: "Divya Prasad",
    email: "divya@meridian.test",
    consent: true,
  });
  expect(String(posted[0].message)).toContain("Meridian Logistics");
});

test("the homepage points employers at the page", async ({ page }) => {
  await page.goto("/");
  await page.getByRole("link", { name: "See how hiring works" }).click();
  await expect(page).toHaveURL(/\/for-employers$/);
});

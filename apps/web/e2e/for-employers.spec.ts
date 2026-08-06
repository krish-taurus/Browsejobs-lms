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

  // The offer is the first thing on the page, and it is stated in full.
  await expect(page.getByText("Free for 6 months · ₹0").first()).toBeVisible();

  // The walkthrough is a tablist. It advances on its own, so hover first —
  // that pauses autoplay and makes the rest of this deterministic.
  const tablist = page.getByRole("tablist", { name: /step by step/i });
  await tablist.hover();

  await tablist.getByRole("tab", { name: /Design the assessment/ }).click();
  const panel = page.getByRole("tabpanel");
  await expect(panel.getByText("Assessment designer")).toBeVisible();

  // Arrow keys move between steps without a mouse.
  await page.keyboard.press("ArrowDown");
  await expect(tablist.getByRole("tab", { selected: true })).toContainText("Design the rounds");

  // Proctoring is claimed, and the page shows what is actually recorded.
  await expect(
    page.getByRole("heading", { name: /Every interview is proctored/i }),
  ).toBeVisible();
  await expect(page.getByText("Left the session window")).toBeVisible();

  // The sample report shows the states a sales page would hide.
  await expect(page.getByText("Contact details unlock at Shortlisted.")).toBeVisible();
  await expect(page.getByText("Sample · fictional candidate").first()).toBeVisible();

  // The honest-limits column is on the page, not buried.
  await expect(page.getByText("Where it falls short")).toBeVisible();

  // Enquiry: the CTA scrolls to the form, and the form posts an employer lead.
  const posted: Record<string, unknown>[] = [];
  // The client fetches the Sanctum CSRF cookie before any unsafe request, so
  // stubbing only the POST leaves the form failing on a connection error to an
  // API that is not running in this suite.
  await page.route("**/sanctum/csrf-cookie", (route) => route.fulfill({ status: 204, body: "" }));
  await page.route("**/api/v1/leads", async (route) => {
    posted.push(route.request().postDataJSON());
    await route.fulfill({ status: 201, json: { status: "received", id: 1 } });
  });

  await page.getByRole("button", { name: "Start free for 6 months" }).first().click();
  await page.getByLabel("Company").fill("Meridian Logistics");
  await page.getByLabel("Your name").fill("Divya Prasad");
  await page.getByLabel("Work email").fill("divya@meridian.test");
  await page.getByLabel("Phone", { exact: true }).fill("+919000000001");
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

test("the walkthrough plays itself, then hands over on a click", async ({ page }) => {
  await page.goto("/for-employers");
  const tablist = page.getByRole("tablist", { name: /step by step/i });
  const selected = tablist.getByRole("tab", { selected: true });

  // Left alone, it moves on without being touched.
  await expect(selected).toContainText("Open a workspace");
  await expect(selected).not.toContainText("Open a workspace", { timeout: 12_000 });

  // Once the visitor picks a step, it stays there.
  await tablist.getByRole("tab", { name: /Publish, and invite/ }).click();
  await page.waitForTimeout(9_000);
  await expect(selected).toContainText("Publish, and invite");
});

test("the sticky CTA does not offer a hiring manager a masterclass", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/for-employers");
  await page.mouse.wheel(0, 900);

  await expect(page.getByRole("button", { name: "Book Free Masterclass" })).toHaveCount(0);
});

test("the homepage points employers at the page", async ({ page }) => {
  await page.goto("/");
  await page.getByRole("link", { name: "See how hiring works" }).click();
  await expect(page).toHaveURL(/\/for-employers$/);
});

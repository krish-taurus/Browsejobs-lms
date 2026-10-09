import { expect, test } from "@playwright/test";

test.beforeEach(async ({ page }) => {
  if (process.env.CI) {
    await page.route("**/api/enquiries", (route) =>
      route.fulfill({
        status: 201,
        contentType: "application/json",
        body: JSON.stringify({ status: "received", id: 1 }),
      }),
    );
  }
});

test("employer enquiry page validates, then submits", async ({ page }) => {
  await page.goto("/employers/enquire?path=partner");
  await expect(page).toHaveTitle("Enquire to hire · BrowseJobs");
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", "https://browsejobs.ai/employers/enquire");
  await expect(page.getByRole("heading", { level: 1 })).toHaveText("Enquire to hire.");
  await expect(page.getByText("Onboard us as your hiring partner")).toBeVisible();

  await page.getByRole("button", { name: "Send enquiry" }).click();
  await expect(page.getByText("Enter your full name.")).toBeVisible();

  await page.getByLabel("Full name").fill("Ada North");
  await page.getByLabel("Work email", { exact: true }).fill("ada@northwind.example");
  await page.getByLabel("Phone", { exact: true }).fill("9840011111");
  await page.getByLabel("Company", { exact: true }).fill("Northwind");
  await page.getByLabel("Company size").selectOption("11-50");
  await page.getByLabel("Roles and number of hires").fill("2 backend engineers");
  await page.getByLabel("City or remote").fill("Bengaluru");
  await page.getByLabel("Hiring timeline").selectOption("this-month");
  await page.getByRole("checkbox", { name: /may call or email/ }).check();

  await page.waitForTimeout(3500);
  await page.getByRole("button", { name: "Send enquiry" }).click();
  await expect(page.getByRole("heading", { name: "Thank you." })).toBeVisible();

  const layer = await page.evaluate(() => JSON.stringify((window as unknown as { dataLayer?: unknown[] }).dataLayer ?? []));
  expect(layer).toContain("generate_lead");
  expect(layer).toContain("employer");
});

test("course enquiry page lists live courses and submits", async ({ page }) => {
  await page.goto("/courses/enquire?course=data-engineering");
  await expect(page).toHaveTitle("Ask about a course · BrowseJobs");
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", "https://browsejobs.ai/courses/enquire");
  await expect(page.getByRole("heading", { level: 1 })).toHaveText("Ask about a course.");

  const course = page.getByLabel("Course of interest");
  await expect(course).toHaveValue("data-engineering");
  const labels = await course.locator("option").allTextContents();
  expect(labels.join(" ")).not.toMatch(/agentic/i);
  expect(labels).toEqual(expect.arrayContaining(["Data Engineering", "DevOps & Cloud", "Python Backend", "Data Analytics"]));

  await page.getByLabel("Full name").fill("Meera Shah");
  await page.getByLabel("Email", { exact: true }).fill("meera@example.test");
  await page.getByLabel("Phone", { exact: true }).fill("9840022222");
  await page.getByLabel("Where you are now").selectOption("working");
  await page.getByLabel("City", { exact: true }).fill("Pune");
  await page.getByLabel("Preferred time to call").selectOption("evening");
  await page.getByRole("checkbox", { name: /may call or email/ }).check();

  await page.waitForTimeout(3500);
  await page.getByRole("button", { name: "Send enquiry" }).click();
  await expect(page.getByRole("heading", { name: "Thank you." })).toBeVisible();

  const layer = await page.evaluate(() => JSON.stringify((window as unknown as { dataLayer?: unknown[] }).dataLayer ?? []));
  expect(layer).toContain("generate_lead");
  expect(layer).toContain("course");
});

test("enquiry pages are in the sitemap and linked from the product pages", async ({ page, request }) => {
  const xml = await (await request.get("/sitemap.xml")).text();
  expect(xml).toContain("https://browsejobs.ai/employers/enquire");
  expect(xml).toContain("https://browsejobs.ai/courses/enquire");
  expect(xml).not.toContain("/courses/agentic-ai");

  await page.goto("/courses");
  await expect(page.locator("#main").getByRole("link", { name: "Ask about a course" }).first()).toHaveAttribute("href", "/courses/enquire");

  await page.goto("/courses/data-engineering");
  await expect(page.getByRole("link", { name: "Ask about this course" }).first()).toHaveAttribute(
    "href",
    "/courses/enquire?course=data-engineering",
  );
});

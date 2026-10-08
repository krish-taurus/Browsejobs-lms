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

test("primary nav is the same order on desktop and mobile", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/");
  const nav = page.getByRole("navigation", { name: "Primary" });
  await expect(nav.getByRole("link", { name: "Home", exact: true })).toHaveAttribute("href", "/");
  await expect(nav.getByRole("link", { name: "Students", exact: true })).toHaveAttribute("href", "/students");
  await expect(nav.getByRole("link", { name: "For Employers", exact: true })).toHaveAttribute("href", "/employers");
  await expect(nav.getByRole("link", { name: "Demo", exact: true })).toHaveAttribute("href", "/demo");
  await expect(nav.getByRole("button", { name: "Login" })).toBeVisible();
  await expect(nav.getByRole("link", { name: "Onboard with us for the future of hiring" })).toHaveAttribute(
    "href",
    "/employers/enquire",
  );

  const centers = await nav.locator(".apple-nav-brand, .apple-nav-links a, .apple-nav-links button, .apple-nav-cta").evaluateAll((nodes) =>
    nodes.map((node) => {
      const box = node.getBoundingClientRect();
      return box.y + box.height / 2;
    }),
  );
  expect(centers.length).toBeGreaterThan(4);
  for (const center of centers) {
    expect(Math.abs(center - centers[0])).toBeLessThan(2);
  }

  await nav.getByRole("button", { name: "Login" }).click();
  await expect(page.getByRole("menuitem", { name: /Job seeker/ })).toBeVisible();

  await page.setViewportSize({ width: 390, height: 844 });
  await page.getByRole("button", { name: "Menu" }).click();
  const menu = page.locator("#apple-menu");
  await expect(menu.getByRole("link", { name: "Students" })).toBeVisible();
  await expect(menu.getByRole("link", { name: "For Employers" })).toBeVisible();
  await expect(menu.getByRole("link", { name: "Onboard with us for the future of hiring" })).toHaveAttribute(
    "href",
    "/employers/enquire",
  );
});

test("students page explains 75 percent, the roadmap, and a counselling callback", async ({ page }) => {
  await page.goto("/students");
  await expect(page.getByRole("heading", { level: 1 })).toHaveCount(1);
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", "https://browsejobs.ai/students");
  await expect(page.locator("#what-75")).toContainText("almost 60%");
  await expect(page.locator("#what-75")).toContainText("3,000 HR recruiters");
  await expect(page.locator("#what-75")).toContainText("Based on BrowseJobs internal data");

  const road = page.locator("#below-75");
  await expect(road).toContainText("Score under 75%");
  await expect(road).toContainText("Free counselling session");
  await expect(road).toContainText("personal improvement plan");
  await expect(road).toContainText("Retake the AI interview");
  await expect(road).toContainText("3,000 HRs");

  const form = page.locator("#counselling");
  await form.getByLabel("Full name").fill("Ada North");
  await form.getByLabel("Email", { exact: true }).fill("ada@example.test");
  await form.getByLabel("Phone", { exact: true }).fill("9840011111");
  await form.getByLabel("City", { exact: true }).fill("Bengaluru");
  await form.getByLabel("Preferred time to call").selectOption("morning");
  await form.getByRole("checkbox", { name: /may call or email/ }).check();
  await page.waitForTimeout(3500);
  await form.getByRole("button", { name: "Request a callback" }).click();
  await expect(form.getByRole("heading", { name: "Thank you." })).toBeVisible();

  const html = await page.content();
  expect(html).toContain('"@type":"FAQPage"');
  expect(html).not.toContain("/courses/agentic-ai");
});

test("employers page explains 90 days against 3 days", async ({ page }) => {
  await page.goto("/employers");
  const impact = page.locator("#impact");
  await expect(impact).toContainText("90 days");
  await expect(impact).toContainText("3 days");
  await expect(impact).toContainText("Sourcing");
  await expect(impact).toContainText("We call and screen shortlisted candidates");
  await expect(impact).toContainText("A person always releases the offer");
  await expect(impact.getByRole("link", { name: "Onboard with us for the future of hiring" })).toHaveAttribute(
    "href",
    "/employers/enquire",
  );
  await expect(page.getByText("Sample data").first()).toBeVisible();
});

test("marketing pages do not say coming soon or not live", async ({ page }) => {
  for (const path of ["/", "/students", "/employers", "/employers/how-it-works", "/employers/faq", "/how-it-works", "/employers/mission-control-demo"]) {
    await page.goto(path);
    const text = (await page.locator("body").innerText()).toLowerCase();
    expect(text, path).not.toContain("coming soon");
    expect(text, path).not.toContain("not live");
  }
});

test("students and demo are in the sitemap", async ({ request }) => {
  const response = await request.get("/sitemap.xml");
  expect(response.ok()).toBeTruthy();
  const xml = await response.text();
  expect(xml).toContain("https://browsejobs.ai/students");
  expect(xml).toContain("https://browsejobs.ai/demo");
  expect(xml).not.toContain("/courses/agentic-ai");
});

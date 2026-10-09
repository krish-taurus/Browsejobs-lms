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
  const nav = page.getByRole("navigation", { name: "Global" });
  await expect(nav.getByRole("link", { name: "Home", exact: true })).toHaveAttribute("href", "/");
  await expect(nav.getByRole("link", { name: "Students", exact: true })).toHaveAttribute("href", "/students");
  await expect(nav.getByRole("link", { name: "Courses", exact: true })).toHaveAttribute("href", "/courses");
  await expect(nav.getByRole("link", { name: "For Employers", exact: true })).toHaveAttribute("href", "/employers");
  await expect(nav.getByRole("link", { name: "Demo", exact: true })).toHaveAttribute("href", "/employers/mission-control-demo");
  const order = await nav.locator(".gnav-links a").evaluateAll((nodes) => nodes.map((node) => (node.textContent ?? "").trim()));
  expect(order).toEqual(["Home", "Students", "Courses", "For Employers", "Demo"]);
  await nav.getByText("Log in").click();
  await expect(nav.getByRole("link", { name: "Job seeker" })).toHaveAttribute("href", "/student");
  await expect(nav.getByRole("link", { name: "Employer", exact: true })).toHaveAttribute("href", "/employer");

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/");
  await page.getByRole("button", { name: /Menu/ }).click();
  const menu = page.getByRole("dialog", { name: "Menu" });
  const mobile = await menu.locator("li a").evaluateAll((nodes) => nodes.map((node) => (node.textContent ?? "").trim()));
  expect(mobile.slice(0, 5)).toEqual(["Home", "Students", "Courses", "For Employers", "Demo"]);
  await expect(menu.getByRole("link", { name: "Job seeker login" })).toHaveAttribute("href", "/student");
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
  expect(html).toContain('"@type":"ItemList"');
  expect(html).toContain('"@type":"EducationalOrganization"');
  // Google reviews are shown, not marked up: self-serving review markup (ratings
  // from Google Maps re-published on our own site) risks a manual action.
  expect(html).not.toContain('"@type":"AggregateRating"');
  expect(html).not.toContain('"@type":"Review"');
  expect(html).not.toContain("/courses/agentic-ai");

  const courses = page.locator("#career-courses");
  await expect(courses).toContainText("Career-driven courses");
  await expect(courses).toContainText("Data Engineering");
  await expect(courses).toContainText("6 months");
  await expect(courses).toContainText("Live online + recordings");
  await expect(courses).toContainText("APIs, databases, and production Python.");
  await expect(courses.getByRole("link", { name: "View course" }).first()).toHaveAttribute("href", "/courses/data-engineering");
  await expect(courses.getByRole("link", { name: "Explore courses" })).toHaveAttribute("href", "/courses");
  await expect(page.locator('a[href="/courses/agentic-ai"]')).toHaveCount(0);

  const stories = page.locator("#success-stories");
  await expect(stories).toContainText("Story coming soon");
  await expect(stories).toContainText("From a 3.5-year support role to an Accenture offer");
  await expect(stories).toContainText("Stuck after a CS post-grad");
  await expect(stories).toContainText("Pranjal");
  await expect(stories).not.toContainText("15LPA");
  await expect(stories).not.toContainText("LPA");
  await expect(stories).toContainText("From homemaker to engineer");
  await expect(stories).toContainText("From delivery rider to engineer");
  await expect(stories).toContainText("AI interview");
  await expect(stories.getByRole("link", { name: "4.9 on Google · 473 reviews" })).toHaveAttribute("href", /google\.com\/maps\/place\/Browsejobs/);
  await expect(stories.getByText("Vinod Karan Singh").first()).toBeVisible();
  await expect(stories.getByText("Google review").first()).toBeVisible();
  await stories.getByRole("button", { name: "Read more" }).first().evaluate((button: HTMLButtonElement) => button.click());
  await expect(stories.getByText(/require assistance in job placement/)).toBeVisible();

  const messages = page.locator("#real-messages");
  await expect(messages.getByRole("heading", { name: "Real messages from our students." })).toBeVisible();
  await expect(messages.getByRole("figure")).toHaveCount(14);
  await expect(messages.getByRole("figure").first()).toContainText("Joining third company after course");
  await expect(messages.getByRole("figure").nth(1)).toContainText("Support role to Accenture offer");
  await stories.getByRole("button", { name: "See the message" }).first().click();
  await expect(page.getByRole("dialog")).toContainText("Support role to Accenture offer");
  await page.keyboard.press("Escape");
  await expect(page.getByRole("dialog")).toHaveCount(0);
});

test("homepage leads with real success stories", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/");
  const stories = page.locator("#stories");
  await expect(stories.getByRole("heading", { name: "Success stories." })).toBeVisible();
  await expect(stories).toContainText("Accenture");
  await expect(stories.getByRole("link", { name: /See the message/ }).first()).toHaveAttribute("href", "/reviews");
});

test("employers page explains 90 days against 3 days", async ({ page }) => {
  await page.goto("/employers");
  const days = page.locator("#days");
  await expect(days).toContainText("90 days");
  await expect(days).toContainText("3 days");
  await expect(page.locator("#get-started a[href='/employers/enquire?path=partner']")).toBeVisible();
  await expect(page.getByText(/sample data/i).first()).toBeAttached();
});

test("marketing pages do not say coming soon or not live", async ({ page }) => {
  for (const path of ["/", "/students", "/employers", "/employers/how-it-works", "/employers/faq", "/how-it-works", "/employers/mission-control-demo", "/courses"]) {
    await page.goto(path);
    if (path === "/" || path === "/students") {
      await expect(page.locator("#success-stories, #stories").first()).toBeVisible();
    }
    const story = page.locator("#success-stories, #stories").first();
    const storyText = (await story.count()) > 0 ? (await story.innerText()).toLowerCase() : "";
    const waitlist = page.locator("#course-waitlist");
    const waitText = (await waitlist.count()) > 0 ? (await waitlist.innerText()).toLowerCase() : "";
    const body = (await page.locator("body").innerText()).toLowerCase();
    let text = body;
    if (storyText) text = text.replaceAll(storyText, "");
    if (waitText) text = text.replaceAll(waitText, "");
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

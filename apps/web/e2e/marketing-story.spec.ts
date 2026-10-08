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
  await expect(nav.getByRole("link", { name: "Courses", exact: true })).toHaveAttribute("href", "/courses");
  await expect(nav.getByRole("link", { name: "For Employers", exact: true })).toHaveAttribute("href", "/employers");
  await expect(nav.getByRole("link", { name: "Demo", exact: true })).toHaveAttribute("href", "/demo");
  const order = await nav.locator(".apple-nav-links a, .apple-nav-links button").evaluateAll((nodes) =>
    nodes.map((node) => (node.textContent ?? "").replace("▾", "").replace(/\s+/g, " ").trim()),
  );
  expect(order.slice(0, 6)).toEqual(["Home", "Students", "Courses", "For Employers", "Demo", "Login"]);
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
  await expect(menu.getByRole("link", { name: "Courses", exact: true })).toHaveAttribute("href", "/courses");
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
  expect(html).toContain('"@type":"ItemList"');
  expect(html).toContain('"@type":"EducationalOrganization"');
  expect(html).toContain('"@type":"AggregateRating"');
  expect(html).toContain('"reviewCount":"473"');
  expect(html).toContain('"@type":"Review"');
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

test("homepage leads with the career-switch messages", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/");
  const messages = page.locator("#real-messages");
  await expect(messages.getByRole("heading", { name: "Real messages from our students." })).toBeVisible();
  const figures = messages.getByRole("figure");
  await expect(figures).toHaveCount(14);
  await expect(figures.nth(0)).toContainText("Joining third company after course");
  await expect(figures.nth(0)).toContainText("Pranjal");
  await expect(figures.nth(1)).toContainText("Support role to Accenture offer");
  await expect(figures.nth(1).locator(".apple-shot-name")).toHaveCount(0);
  await expect(messages.getByRole("link", { name: "Take the free AI interview" })).toHaveAttribute("href", "/#interview-start");
  await expect(messages.locator("img").first()).toHaveAttribute("loading", "lazy");
  await figures.nth(0).getByRole("button").click();
  const dialog = page.getByRole("dialog");
  await expect(dialog).toContainText("Joining third company after course");
  await expect(dialog).toContainText("Pranjal");
  await page.keyboard.press("Escape");
  await expect(dialog).toHaveCount(0);
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
  for (const path of ["/", "/students", "/employers", "/employers/how-it-works", "/employers/faq", "/how-it-works", "/employers/mission-control-demo", "/courses"]) {
    await page.goto(path);
    if (path === "/" || path === "/students") {
      await expect(page.locator("#success-stories")).toBeVisible();
    }
    const story = page.locator("#success-stories");
    const storyText = (await story.count()) > 0 ? (await story.innerText()).toLowerCase() : "";
    const body = (await page.locator("body").innerText()).toLowerCase();
    const text = storyText ? body.replaceAll(storyText, "") : body;
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

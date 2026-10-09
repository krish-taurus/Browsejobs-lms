import { expect, test } from "@playwright/test";

/**
 * Homepage opens on one action: a free AI interview. Counselling and courses
 * stay further down the page.
 */
test("homepage hero offers the free AI interview above the fold", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const response = await page.goto("/");
  expect(response?.ok()).toBeTruthy();

  const hero = page.locator("#top");
  await expect(hero.getByRole("heading", { level: 1 })).toContainText(/Take a free\s+AI interview/i);
  await expect(hero).toContainText("about 15 questions");
  await expect(hero).toContainText("75%");
  await expect(hero).toContainText("3,000 HR recruiters");

  const phone = hero.getByLabel("Phone number");
  const cta = hero.getByRole("button", { name: "Take your free AI interview" });
  await expect(phone).toBeVisible();
  await expect(cta).toBeVisible();

  const ctaBox = await cta.boundingBox();
  expect(ctaBox).not.toBeNull();
  expect(ctaBox!.y + ctaBox!.height).toBeLessThanOrEqual(844);

  await cta.click();
  await expect(page.getByRole("dialog")).toHaveCount(0);
  await expect(page).toHaveURL("/");

  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", "https://browsejobs.ai");

  await page.goto("/how-it-works");
  const gaps = page.locator("#gaps");
  await expect(gaps).toContainText("blocking interviews");
  await expect(gaps.getByRole("button", { name: "Book free counselling" })).toBeVisible();

  for (const slug of ["data-engineering", "devops-cloud", "python-backend", "data-analytics"]) {
    await expect(page.locator(`#courses a[href="/courses/${slug}"]`).first()).toBeVisible();
  }

  await expect(page.locator('a[href="/courses/agentic-ai"]')).toHaveCount(0);
  await expect(page.locator('a[href="/courses/cyber-security"]')).toHaveCount(0);
  await expect(page.locator('a[href="/courses/servicenow"]')).toHaveCount(0);

  const main = page.locator("main");
  await expect(main).not.toContainText("98%");
  await expect(main).not.toContainText("100% placement");
  await expect(main).not.toContainText("LPA");
  await expect(main).not.toContainText("guaranteed job");
});

test("desktop hero keeps the interview form as the first action", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/");

  const hero = page.locator("#top");
  const cta = hero.getByRole("button", { name: "Take your free AI interview" });
  await expect(cta).toBeVisible();
  const box = await cta.boundingBox();
  expect(box).not.toBeNull();
  expect(box!.y + box!.height).toBeLessThanOrEqual(800);

  await expect(page.getByRole("navigation", { name: "Global" }).getByRole("link", { name: "Take the free AI interview" })).toHaveAttribute(
    "href",
    "/#top",
  );
});

test("how it works walks the five steps, then the candidate film", async ({ page }) => {
  for (const width of [390, 1280]) {
    await page.setViewportSize({ width, height: 800 });
    await page.goto("/");
    const journey = page.locator("#journey");
    await expect(journey).toContainText("Share your CV.");
    await expect(journey).toContainText("HR sees your CV");
    await expect(journey).toContainText("Get a call for an interview.");
    const film = page.locator("#journey-film video");
    await expect(film).toHaveAttribute("src", "/media/journey/candidate-journey.mp4");
    await expect(film).toHaveAttribute("preload", "none");
  }

  await page.goto("/how-it-works");
  await expect(page.locator("#how")).toContainText("almost 60%");
  await expect(page.locator("#how")).toContainText("3,000 HR recruiters");
});

test("homepage employer CTA opens the employer page", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/");
  const cta = page.getByRole("navigation", { name: "Global" }).getByRole("link", { name: "For Employers" });
  await cta.scrollIntoViewIfNeeded();
  await cta.click();
  await expect(page).toHaveURL(/\/employers$/);
  await expect(page.getByRole("heading", { level: 1 })).toContainText(/Your AI Recruiter/i);
});

test("counselling modal opens from the miss path", async ({ page }) => {
  await page.goto("/how-it-works");
  const counselling = page.locator("#gaps").getByRole("button", { name: "Book free counselling" });
  await counselling.scrollIntoViewIfNeeded();
  await counselling.click();
  const dialog = page.getByRole("dialog");
  await expect(dialog).toBeVisible();
  await expect(dialog).toContainText("Free counselling");
});

import { expect, test } from "@playwright/test";

test("employer page keeps its canonical, FAQ, and service schema", async ({ page }) => {
  const response = await page.goto("/employers");
  expect(response?.ok()).toBeTruthy();

  await expect(page).toHaveTitle(/Hire in 3 days, not 90/);
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", "https://browsejobs.ai/employers");
  await expect(page.getByRole("heading", { level: 1 })).toContainText(/Your AI Recruiter/i);

  const html = await page.content();
  expect(html).toContain('"@type":"FAQPage"');
  expect(html).toContain('"@type":"Service"');
  expect(html).not.toContain("/courses/agentic-ai");
  expect(html).toContain("Powered by Taurus AI");
  // The product stays "BrowseJobs AI Recruiter"; Taurus appears as the engine and in the hiring-story film.
  await expect(page.locator('#taurus-demo a[href="/taurusai/hiring-demo"]')).toBeAttached();
  expect(html).not.toContain("guaranteed job");
  expect(html).not.toContain("100% placement");

  await expect(page.getByText("75%").first()).toBeVisible();
  await expect(page.getByText("90 days").first()).toBeVisible();
  await expect(page.getByText("3 days").first()).toBeVisible();
  await expect(page.getByText("You approve the offer.").first()).toBeVisible();
  await expect(page.getByText(/A person must always approve and send the offer letter/).first()).toBeVisible();

  await page.goto("/employers/how-it-works");
  await expect(page.getByRole("heading", { name: "Screening Bot" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Interview Bot" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "BGV Bot" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Candidate Interaction Bot" })).toBeVisible();
});

test("employer page offers both ways to work and a labelled sample report", async ({ page }) => {
  await page.goto("/employers");

  const partner = page.locator('#get-started a[href="/employers/enquire?path=partner"]');
  const tool = page.locator('#get-started a[href="/employers/enquire?path=tool"]');
  await expect(partner).toContainText("We hire for you");
  await expect(tool).toContainText("You use our tool");

  await page.goto("/employers/how-it-works");
  const report = page.locator("#report");
  await expect(report).toContainText("Sample report: example candidate");
  await expect(report).toContainText("Sample Candidate");
  await expect(report).toContainText("Example Retail Co.");
  await expect(report).toContainText("82");
  await expect(report).toContainText("Face present");
  await expect(report).toContainText("Not a real person");
});

test("employers stays in the sitemap", async ({ request }) => {
  const response = await request.get("/sitemap.xml");
  expect(response.ok()).toBeTruthy();
  const xml = await response.text();
  expect(xml).toContain("https://browsejobs.ai/employers");
  expect(xml).toContain("https://browsejobs.ai/employers/how-it-works");
  expect(xml).toContain("https://browsejobs.ai/employers/faq");
  expect(xml).toContain("https://browsejobs.ai/how-it-works");
});

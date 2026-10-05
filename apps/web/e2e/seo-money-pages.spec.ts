import { expect, test } from "@playwright/test";
import { seoMoneyLinks } from "../src/content/seo-nav";

/**
 * Money landing pages must be crawlable: 200, one H1, a self-canonical,
 * FAQPage JSON-LD, and a sitemap entry. Course pages also emit Course schema.
 */

test("sitemap lists every money page", async ({ request }) => {
  const response = await request.get("/sitemap.xml");
  expect(response.ok()).toBeTruthy();
  const xml = await response.text();
  for (const page of seoMoneyLinks) {
    expect(xml).toContain(`https://browsejobs.ai${page.path}`);
  }
});

for (const page of seoMoneyLinks) {
  test(`${page.path} is indexable HTML`, async ({ page: browserPage }) => {
    const response = await browserPage.goto(page.path);
    expect(response?.ok()).toBeTruthy();

    await expect(browserPage.getByRole("heading", { level: 1 })).toHaveText(page.title);

    const canonical = browserPage.locator('link[rel="canonical"]');
    await expect(canonical).toHaveAttribute("href", `https://browsejobs.ai${page.path}`);

    const html = await browserPage.content();
    expect(html).toContain('"@type":"FAQPage"');
    expect(html).toContain(page.title);

    if (page.path.startsWith("/data-engineering-course")) {
      expect(html).toContain('"@type":"Course"');
    }
  });
}

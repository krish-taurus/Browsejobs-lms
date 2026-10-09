import { expect, test } from "@playwright/test";

/**
 * Public Taurus demos (/taurusai, /taurusai/recruitment): the HUD must render
 * and keep updating even where WebGL is unavailable (CI's headless Chromium),
 * every number must be labelled as sample data, and the look switcher works.
 */

test("agent floor demo renders a labelled, live HUD", async ({ page }) => {
  await page.goto("/taurusai");
  const floor = page.locator(".tc").first();
  await expect(floor).toBeVisible();
  await expect(floor.getByText("SAMPLE DATA")).toBeVisible();
  await expect(floor.locator(".tc-kpi")).toHaveCount(4);
  await expect(floor.locator(".tc-kpi").first()).toContainText("Running now");
  await expect(floor.locator(".tc-feed li").first()).toBeVisible();

  const noir = floor.getByRole("button", { name: /Noir/ });
  await noir.click();
  await expect(noir).toHaveAttribute("aria-pressed", "true");
  await expect(floor).toHaveAttribute("data-look", "noir");
});

test("agent floor demo answers Ask Taurus from the simulation", async ({ page }) => {
  await page.goto("/taurusai");
  const floor = page.locator(".tc").first();
  await floor.getByLabel("Ask Taurus", { exact: true }).fill("What needs me?");
  await floor.getByRole("button", { name: "Ask", exact: true }).click();
  await expect(floor.locator(".tc-answer")).toContainText("sample demo");
});

test("hiring story plays the WhatsApp flow and accepts a reply", async ({ page }) => {
  await page.goto("/taurusai/recruitment");
  const floor = page.locator(".tc").first();
  await expect(floor.locator(".tc-stage")).toHaveCount(10);
  await expect(floor.locator(".tc-phone")).toContainText("5 software developers in Bangalore");
  const reply = floor.locator(".tc-replies button").first();
  await expect(reply).toBeVisible({ timeout: 30_000 });
  const label = (await reply.innerText()).replace(/\s*\d+s$/, "");
  await reply.click();
  await expect(floor.locator(".tc-bubble.me").last()).toContainText(label);
  await expect(floor.locator(".tc-caption")).toContainText("Step");
});

test("hiring floor demo shows all eight stages", async ({ page }) => {
  await page.goto("/taurusai/recruitment");
  await page.getByRole("tab", { name: "The live hiring floor" }).click();
  const floor = page.locator(".tc").first();
  const stages = floor.locator(".tc-stage");
  await expect(stages).toHaveCount(8);
  await expect(stages.first()).toContainText("Applied");
  await expect(stages.last()).toContainText("Hired");
  await expect(floor.getByText("Hiring bots")).toBeVisible();
});

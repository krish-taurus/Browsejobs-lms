import { expect, test, type Page } from "@playwright/test";

/**
 * /employers — the hiring-team landing page. The seven pipeline demos only play
 * once their scene scrolls into view, so each assertion scrolls first and then
 * waits for the reveal to settle at full opacity. Opacity is checked explicitly
 * because Playwright treats an opacity-0 element as "visible".
 */

const STAGES = ["jd", "shortlist", "call", "rounds", "ats", "bgv", "report"] as const;

/** Scroll a scene into view and let its in-view sequence play out. */
async function openStage(page: Page, id: string) {
  await page.evaluate((sel) => document.querySelector(sel)?.scrollIntoView(), `#stage-${id}`);
  await page.waitForTimeout(2500);
}

test("employers landing renders every pipeline stage", async ({ page }) => {
  const response = await page.goto("/employers");
  expect(response?.ok()).toBeTruthy();

  await expect(page.getByRole("heading", { level: 1 })).toContainText("Interview the shortlist.");

  for (const id of STAGES) {
    await expect(page.locator(`#stage-${id}`)).toHaveCount(1);
  }
});

test("each stage demo animates itself into view", async ({ page }) => {
  await page.goto("/employers");

  // 01 — the JD types itself in, then the extracted fields appear.
  await openStage(page, "jd");
  await expect(page.locator("#stage-jd")).toContainText("Senior QA Engineer in Bengaluru");
  await expect(page.locator("#stage-jd").getByText("Selenium", { exact: false }).first()).toBeVisible();

  // 02 — ranked shortlist rows slide in at full opacity.
  await openStage(page, "shortlist");
  const firstCandidate = page.locator("#stage-shortlist").getByText("R. Krishnan").first();
  await expect(firstCandidate).toBeVisible();
  await expect
    .poll(async () => firstCandidate.evaluate((el) => getComputedStyle(el.closest("div")!).opacity))
    .toBe("1");

  // 03 — screening call check-list resolves.
  await openStage(page, "call");
  await expect(page.locator("#stage-call")).toContainText("Notice period");
  await expect(page.locator("#stage-call")).toContainText("15 days");

  // 04 — the round tabs cycle; L1 is the opening state.
  await openStage(page, "rounds");
  await expect(page.locator("#stage-rounds").getByRole("button", { name: "L2" })).toBeVisible();
  await page.locator("#stage-rounds").getByRole("button", { name: "Custom" }).click();
  await expect(page.locator("#stage-rounds")).toContainText("Your round, your rubric");

  // 05 — the board moves: cards leave "Screened" and land further along.
  await openStage(page, "ats");
  await expect
    .poll(async () =>
      page.evaluate(() => {
        const cols = [...document.querySelectorAll("#stage-ats .grid-cols-4 > div")];
        return cols.map((c) => c.querySelectorAll("p.truncate").length);
      }),
    )
    .not.toEqual([5, 0, 0, 0]);

  // 06 — evidence panel surfaces three clean checks and one proctoring flag.
  await openStage(page, "bgv");
  await expect(page.locator("#stage-bgv")).toContainText("Review — 2 events in L2");
  await expect(page.locator("#stage-bgv")).toContainText("A flag never auto-rejects");

  // 07 — the brief assembles and is marked sent.
  await openStage(page, "report");
  await expect(page.locator("#stage-report")).toContainText("Progress to panel");
});

test("employer enquiry form captures a lead", async ({ page }) => {
  // Collected into an array so the assertions below read the captured value
  // without control-flow narrowing it back to its initial null.
  const payloads: Record<string, unknown>[] = [];

  await page.route("**/api/v1/leads", async (route) => {
    payloads.push(route.request().postDataJSON());
    await route.fulfill({ status: 201, contentType: "application/json", body: JSON.stringify({ status: "received", id: 1 }) });
  });
  await page.route("**/sanctum/csrf-cookie", (route) => route.fulfill({ status: 204, body: "" }));

  await page.goto("/employers");
  await page.evaluate(() => document.querySelector("#start")?.scrollIntoView());

  const form = page.locator("#start form");
  const submit = form.getByRole("button", { name: /Start the 6 free months/ });

  // DPDP: the button stays disabled until consent is ticked.
  await expect(submit).toBeDisabled();

  await form.getByPlaceholder("Your name").fill("Meera Iyer");
  await form.getByPlaceholder("Company").fill("Northwind Technologies");
  await form.getByPlaceholder("Work phone").fill("+919000000002");
  await form.getByPlaceholder("Work email").fill("meera@northwind.test");
  await form.getByRole("combobox").selectOption("6–20 roles");
  await form.getByPlaceholder(/^Roles/).fill("QA, backend");

  await expect(submit).toBeDisabled();
  await form.getByRole("checkbox").check();
  await expect(submit).toBeEnabled();

  await submit.click();

  // Success navigates to the shared confirmation page, not an inline state.
  await expect(page).toHaveURL(/\/thank-you\?type=employer$/);
  await expect(page.getByRole("heading", { level: 1 })).toContainText("hiring enquiry");

  expect(payloads).toHaveLength(1);
  expect(payloads[0]).toMatchObject({
    lead_type: "employer",
    name: "Meera Iyer",
    company: "Northwind Technologies",
    page: "/employers",
    consent: true,
  });
  expect(String(payloads[0].message)).toContain("6–20 roles");
});

test("the page is reachable from the home page nav", async ({ page }) => {
  await page.goto("/");
  await page.getByRole("link", { name: "For employers" }).first().click();
  await expect(page).toHaveURL(/\/employers$/);
});
